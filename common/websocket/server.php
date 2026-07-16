<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../common/config/bootstrap.php';
require_once __DIR__ . '/../../console/config/bootstrap.php';

defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');

use yii\helpers\ArrayHelper;
use React\EventLoop\Loop;
use React\Socket\SocketServer;
use React\Http\HttpServer;
use React\Http\Message\Response;
use Psr\Http\Message\ServerRequestInterface;
use common\websocket\ChatServer;

// Merge Yii configs
$config = ArrayHelper::merge(
    require __DIR__ . '/../../common/config/main.php',
    require __DIR__ . '/../../common/config/main-local.php',
    require __DIR__ . '/../../console/config/main.php',
    require __DIR__ . '/../../console/config/main-local.php'
);

$application = new yii\console\Application($config);
$chatServer = new ChatServer($application);

$loop = Loop::get();
$connections = new \SplObjectStorage();

// HTTP server for POST /notify and GET /health
$httpServer = new HttpServer(function (ServerRequestInterface $request) use ($chatServer) {
    $method = $request->getMethod();
    $uri = $request->getUri()->getPath();

    if ($method === 'POST' && $uri === '/notify') {
        $body = $request->getBody()->getContents();
        $data = json_decode($body, true);
        if ($data && isset($data['type']) && $data['type'] === 'new_message') {
            $chatServer->sendNotification($data['chatId'], $data['message']);
            return new Response(200, ['Content-Type' => 'application/json'], json_encode(['success' => true]));
        }
        return new Response(400, ['Content-Type' => 'application/json'], json_encode(['error' => 'Invalid data']));
    }

    if ($method === 'GET' && $uri === '/health') {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode(['status' => 'ok']));
    }

    return new Response(404, ['Content-Type' => 'application/json'], json_encode(['error' => 'Not found']));
});

$httpSocket = new SocketServer('0.0.0.0:8901', [], $loop);
$httpServer->listen($httpSocket);

// WebSocket server with proper handshake
$wsSocket = new SocketServer('0.0.0.0:8900', [], $loop);

$wsSocket->on('connection', function ($conn) use ($chatServer, $connections) {
    $connections->attach($conn);

    echo "New raw connection from: " . $conn->getRemoteAddress() . "\n";

    $conn->on('data', function ($data) use ($conn, $chatServer) {
        // Check if this is a WebSocket handshake request
        if (strpos($data, "GET") === 0 && strpos($data, "Upgrade: websocket") !== false) {
            echo "WebSocket handshake request received\n";

            // Parse headers
            $headers = [];
            $lines = explode("\r\n", $data);
            foreach ($lines as $line) {
                if (strpos($line, ':') !== false) {
                    list($key, $value) = explode(':', $line, 2);
                    $headers[strtolower(trim($key))] = trim($value);
                }
            }

            // Validate WebSocket headers
            if (!isset($headers['sec-websocket-key']) ||
                !isset($headers['upgrade']) ||
                strtolower($headers['upgrade']) !== 'websocket') {
                echo "Invalid WebSocket handshake headers\n";
                $conn->close();
                return;
            }

            // Generate WebSocket accept key
            $key = $headers['sec-websocket-key'];
            $acceptKey = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));

            // Send proper WebSocket handshake response
            $response = "HTTP/1.1 101 Switching Protocols\r\n" .
                "Upgrade: websocket\r\n" .
                "Connection: Upgrade\r\n" .
                "Sec-WebSocket-Accept: {$acceptKey}\r\n" .
                "\r\n";

            $conn->write($response);
            echo "WebSocket handshake completed successfully\n";

            // Now initialize the chat server for this connection
            $chatServer->onConnection($conn);
            return;
        }

        // Handle WebSocket frames (after handshake is complete)
        if (strlen($data) >= 2) {
            $frame = parseWebSocketFrame($data);
            if ($frame !== false) {
                // Pass decoded message to chat server
                $chatServer->handleMessage($conn, $frame);
            }
        }
    });

    $conn->on('close', function () use ($conn, $connections, $chatServer) {
        echo "Connection closed: " . $conn->getRemoteAddress() . "\n";
        $connections->detach($conn);
        $chatServer->handleClose($conn);
    });

    $conn->on('error', function ($error) use ($conn) {
        echo "Connection error: " . $error->getMessage() . "\n";
    });
});

// Function to parse WebSocket frames
function parseWebSocketFrame($data) {
    if (strlen($data) < 2) {
        return false;
    }

    $firstByte = ord($data[0]);
    $secondByte = ord($data[1]);

    $fin = ($firstByte >> 7) & 1;
    $opcode = $firstByte & 0x0F;
    $masked = ($secondByte >> 7) & 1;
    $payloadLength = $secondByte & 0x7F;

    $offset = 2;

    // Handle extended payload length
    if ($payloadLength == 126) {
        if (strlen($data) < $offset + 2) return false;
        $payloadLength = unpack('n', substr($data, $offset, 2))[1];
        $offset += 2;
    } elseif ($payloadLength == 127) {
        if (strlen($data) < $offset + 8) return false;
        $payloadLength = unpack('J', substr($data, $offset, 8))[1];
        $offset += 8;
    }

    // Handle masking
    $maskingKey = '';
    if ($masked) {
        if (strlen($data) < $offset + 4) return false;
        $maskingKey = substr($data, $offset, 4);
        $offset += 4;
    }

    // Extract payload
    if (strlen($data) < $offset + $payloadLength) {
        return false;
    }

    $payload = substr($data, $offset, $payloadLength);

    // Unmask payload if needed
    if ($masked) {
        for ($i = 0; $i < $payloadLength; $i++) {
            $payload[$i] = chr(ord($payload[$i]) ^ ord($maskingKey[$i % 4]));
        }
    }

    // Handle different opcodes
    switch ($opcode) {
        case 0x1: // Text frame
            return $payload;
        case 0x8: // Close frame
            return false;
        case 0x9: // Ping frame
            return json_encode(['type' => 'ping']);
        case 0xA: // Pong frame
            return json_encode(['type' => 'pong']);
        default:
            return false;
    }
}

// Function to create WebSocket frame for sending data
function createWebSocketFrame($data, $opcode = 0x1) {
    $dataLength = strlen($data);
    $frame = chr(0x80 | $opcode); // FIN = 1, opcode

    if ($dataLength < 126) {
        $frame .= chr($dataLength);
    } elseif ($dataLength < 65536) {
        $frame .= chr(126) . pack('n', $dataLength);
    } else {
        $frame .= chr(127) . pack('J', $dataLength);
    }

    $frame .= $data;
    return $frame;
}

echo "WebSocket server listening on port 8900\n";
echo "HTTP server listening on port 8901\n";
echo "Server ready to accept connections...\n";

$loop->run();