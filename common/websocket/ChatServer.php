<?php

namespace common\websocket;

use backend\models\Chat;
use backend\models\ChatMessage;
use backend\models\ChatParticipant;
use backend\models\ChatMessageReadStatus;
use backend\models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use SplObjectStorage;

class ChatServer
{
    protected $app;
    protected $connections;
    protected $userConnections;
    protected $userChatRooms;
    protected $chatParticipants;
    protected $jwtSecret;

    public function __construct($app)
    {
        $this->app = $app;
        $this->connections = new SplObjectStorage();
        $this->userConnections = [];
        $this->userChatRooms = [];
        $this->chatParticipants = [];

        $this->jwtSecret = $app->params['websocketSecret'] ?? 'default-secret';
    }

    public function onConnection($connection)
    {
        $remoteAddress = $connection->getRemoteAddress();
        $connectionId = spl_object_id($connection);

        $this->connections->attach($connection, [
            'userId' => null,
            'role' => null,
            'authenticated' => false,
            'handshakeComplete' => true
        ]);

        echo "WebSocket connection established! ID: {$connectionId}, Address: {$remoteAddress}\n";
        echo "Total active connections: " . count($this->connections) . "\n";

        $connection->on('close', function () use ($connection) {
            $this->handleClose($connection);
        });

        $connection->on('error', function ($error) use ($connection) {
            echo "Connection error: " . $error->getMessage() . "\n";
            $this->handleClose($connection);
        });
    }

    public function handleMessage($connection, $data)
    {
        try {
            echo "Received message: " . substr($data, 0, 100) . "...\n";

            $message = json_decode($data, true);

            if (!$message || !isset($message['type'])) {
                echo "Invalid message format\n";
                return;
            }

            echo "Processing message type: " . $message['type'] . "\n";

            switch ($message['type']) {
                case 'auth':
                    $this->handleAuth($connection, $message);
                    break;
                case 'join_chat':
                    $this->handleJoinChat($connection, $message);
                    break;
                case 'new_message':
                    $this->handleNewMessage($message);
                    break;
                case 'message_read':
                    $this->handleMessageRead($message);
                    break;
                case 'ping':
                    $this->sendWebSocketMessage($connection, ['type' => 'pong']);
                    break;
                default:
                    echo "Unknown message type: " . $message['type'] . "\n";
            }
        } catch (\Exception $e) {
            echo "Error handling message: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
        }
    }

    public function handleClose($connection)
    {
        $connectionData = $this->connections[$connection] ?? null;

        if ($connectionData && $connectionData['userId']) {
            $userId = $connectionData['userId'];

            unset($this->userConnections[$userId]);
            unset($this->userChatRooms[$userId]);

            foreach ($this->chatParticipants as $chatId => &$participants) {
                $key = array_search($userId, $participants);
                if ($key !== false) {
                    unset($participants[$key]);
                    $participants = array_values($participants);
                }
            }

            echo "User {$userId} disconnected\n";
        }

        $this->connections->detach($connection);
        echo "Connection closed. Remaining connections: " . count($this->connections) . "\n";
    }

    protected function handleAuth($connection, $message)
    {
        echo "Handling authentication...\n";

        $token = $message['token'] ?? '';
        $decoded = $this->validateJwtToken($token);

        if (!$decoded) {
            echo "Authentication failed: Invalid token\n";
            $this->sendWebSocketMessage($connection, [
                'type' => 'auth_error',
                'message' => 'Invalid or expired token'
            ]);
            $connection->close();
            return;
        }

        $userId = $decoded->userId;
        $userRole = $decoded->role;

        echo "Authenticating user {$userId} with role {$userRole}\n";

        if (isset($this->userConnections[$userId])) {
            $oldConnection = $this->userConnections[$userId];
            echo "Closing previous connection for user {$userId}\n";
            $this->sendWebSocketMessage($oldConnection, [
                'type' => 'connection_replaced',
                'message' => 'New connection established'
            ]);
            $oldConnection->close();
        }

        try {
            $user = User::findOne($userId);
            if (!$user || $user->status != User::STATUS_ACTIVE) {
                echo "User validation failed: User not found or inactive\n";
                $this->sendWebSocketMessage($connection, [
                    'type' => 'auth_error',
                    'message' => 'User not found or inactive'
                ]);
                $connection->close();
                return;
            }
        } catch (\Exception $e) {
            echo "Database error during auth: " . $e->getMessage() . "\n";
            $this->sendWebSocketMessage($connection, [
                'type' => 'auth_error',
                'message' => 'Database connection error'
            ]);
            $connection->close();
            return;
        }

        $this->connections[$connection] = [
            'userId' => $userId,
            'role' => $userRole,
            'authenticated' => true,
            'handshakeComplete' => true
        ];

        $this->userConnections[$userId] = $connection;
        $chatIds = $this->getUserChatIds($userId, $userRole);
        $this->userChatRooms[$userId] = $chatIds;

        foreach ($chatIds as $chatId) {
            if (!isset($this->chatParticipants[$chatId])) {
                $this->chatParticipants[$chatId] = [];
            }
            if (!in_array($userId, $this->chatParticipants[$chatId])) {
                $this->chatParticipants[$chatId][] = $userId;
            }
        }

        $this->sendWebSocketMessage($connection, [
            'type' => 'auth_success',
            'userId' => $userId,
            'chats' => $chatIds
        ]);

        echo "User {$userId} ({$userRole}) authenticated successfully with " . count($chatIds) . " chats\n";
    }

    protected function handleJoinChat($connection, $message)
    {
        $connectionData = $this->connections[$connection] ?? null;

        if (!$connectionData || !$connectionData['authenticated']) {
            echo "Join chat failed: Not authenticated\n";
            return;
        }

        $chatId = $message['chatId'] ?? '';
        $userId = $connectionData['userId'];

        if (!$chatId || !in_array($chatId, $this->userChatRooms[$userId] ?? [])) {
            echo "Join chat failed: Access denied to chat {$chatId} for user {$userId}\n";
            $this->sendWebSocketMessage($connection, [
                'type' => 'error',
                'message' => 'Access denied to chat'
            ]);
            return;
        }

        $this->sendWebSocketMessage($connection, [
            'type' => 'chat_joined',
            'chatId' => $chatId
        ]);

        echo "User {$userId} joined chat {$chatId}\n";
    }

    protected function handleNewMessage($message)
    {
        $chatId = $message['chatId'] ?? '';
        $messageData = $message['message'] ?? [];

        if (!$chatId || !$messageData) {
            echo "New message failed: Missing chatId or message data\n";
            return;
        }

        $participants = $this->chatParticipants[$chatId] ?? [];
        $senderId = $messageData['senderId'] ?? null;

        echo "Broadcasting message to chat {$chatId} for " . count($participants) . " participants\n";

        foreach ($participants as $userId) {
            // ИСКЛЮЧАЕМ ОТПРАВИТЕЛЯ
            if ($userId == $senderId) {
                echo "Skipping sender {$userId}\n";
                continue;
            }

            if (isset($this->userConnections[$userId])) {
                $connection = $this->userConnections[$userId];
                $messageForUser = $messageData;
                $messageForUser['isOwn'] = false;

                $totalUnread = $this->getUnreadCount($userId);
                $chatUnread = $this->getChatUnreadCount($userId, $chatId);

                $this->sendWebSocketMessage($connection, [
                    'type' => 'new_message',
                    'chatId' => $chatId,
                    'message' => $messageForUser,
                    'unreadUpdate' => [
                        'totalUnread' => $totalUnread,
                        'chatUnread' => [
                            'chatId' => $chatId,
                            'count' => $chatUnread
                        ]
                    ]
                ]);

                echo "Message sent to user {$userId}\n";
            } else {
                echo "User {$userId} not connected\n";
            }
        }
    }

    protected function handleMessageRead($message)
    {
        $chatId = $message['chatId'] ?? '';
        $userId = $message['userId'] ?? 0;

        if (!$chatId || !$userId || !isset($this->userConnections[$userId])) {
            return;
        }

        $unreadCount = $this->getUnreadCount($userId);
        $chatUnreadCount = $this->getChatUnreadCount($userId, $chatId);

        $this->sendWebSocketMessage($this->userConnections[$userId], [
            'type' => 'unread_update',
            'totalUnread' => $unreadCount,
            'chatUnread' => [
                'chatId' => $chatId,
                'count' => $chatUnreadCount
            ]
        ]);
    }

    protected function sendWebSocketMessage($connection, array $data)
    {
        try {
            $jsonData = json_encode($data);
            $frame = $this->createWebSocketFrame($jsonData);
            $connection->write($frame);
            return true;
        } catch (\Exception $e) {
            echo "Error sending WebSocket message: " . $e->getMessage() . "\n";
            return false;
        }
    }

    protected function createWebSocketFrame($data, $opcode = 0x1)
    {
        $dataLength = strlen($data);
        $frame = chr(0x80 | $opcode);

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

    protected function validateJwtToken($token)
    {
        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));

            if (isset($decoded->exp) && $decoded->exp < time()) {
                echo "Token expired for user: " . ($decoded->userId ?? 'unknown') . "\n";
                return false;
            }

            if (!isset($decoded->userId) || !isset($decoded->role)) {
                echo "Invalid token structure\n";
                return false;
            }

            return $decoded;
        } catch (\Exception $e) {
            echo "JWT validation error: " . $e->getMessage() . "\n";
            return false;
        }
    }

    protected function getUserChatIds($userId, $userRole)
    {
        $chatIds = [];
        try {
            if ($userRole === 'hr-specialist') {
                $courierChats = Chat::find()->where(['type' => Chat::TYPE_COURIER])->select('id')->column();
                $employeeChats = Chat::find()
                    ->innerJoin('chat_participants cp', 'chats.id = cp.chat_id')
                    ->where([
                        'chats.type' => [Chat::TYPE_EMPLOYEE_PRIVATE, Chat::TYPE_EMPLOYEE_GROUP],
                        'cp.user_id' => $userId,
                        'cp.left_at' => null
                    ])
                    ->select('chats.id')
                    ->column();
                $chatIds = array_merge($courierChats, $employeeChats);
            } elseif ($userRole === 'courier') {
                $chatIds = Chat::find()
                    ->where(['type' => Chat::TYPE_COURIER, 'courier_id' => $userId])
                    ->select('id')
                    ->column();
            } else {
                $chatIds = Chat::find()
                    ->innerJoin('chat_participants cp', 'chats.id = cp.chat_id')
                    ->where([
                        'chats.type' => [Chat::TYPE_EMPLOYEE_PRIVATE, Chat::TYPE_EMPLOYEE_GROUP],
                        'cp.user_id' => $userId,
                        'cp.left_at' => null
                    ])
                    ->select('chats.id')
                    ->column();
            }
        } catch (\Exception $e) {
            echo "Error getting user chat IDs: " . $e->getMessage() . "\n";
        }
        return $chatIds;
    }

    protected function getUnreadCount($userId)
    {
        try {
            return ChatMessageReadStatus::find()
                ->innerJoin('chat_messages cm', 'chat_message_read_status.message_id = cm.id')
                ->where(['chat_message_read_status.user_id' => $userId, 'chat_message_read_status.is_read' => 0, 'cm.is_deleted' => 0])
                ->count();
        } catch (\Exception $e) {
            echo "Error getting unread count: " . $e->getMessage() . "\n";
            return 0;
        }
    }

    protected function getChatUnreadCount($userId, $chatId)
    {
        try {
            return ChatMessageReadStatus::find()
                ->innerJoin('chat_messages cm', 'chat_message_read_status.message_id = cm.id')
                ->where(['chat_message_read_status.user_id' => $userId, 'chat_message_read_status.is_read' => 0, 'cm.chat_id' => $chatId, 'cm.is_deleted' => 0])
                ->count();
        } catch (\Exception $e) {
            echo "Error getting chat unread count: " . $e->getMessage() . "\n";
            return 0;
        }
    }

    public function sendNotification($chatId, $messageData)
    {
        $this->handleNewMessage([
            'chatId' => $chatId,
            'message' => $messageData
        ]);
    }
}