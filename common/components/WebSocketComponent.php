<?php
// common/components/WebSocketComponent.php

namespace common\components;

use Yii;
use yii\base\Component;

class WebSocketComponent extends Component
{
    public $serverUrl = 'http://localhost:8901';
    public $timeout = 3;
    public $connectTimeout = 1;
    public $enabled = true;
    public $retryCount = 2;
    public $logErrors = true;

    /**
     * Send new message notification to WebSocket server
     * Failsafe - never breaks app flow if WebSocket is down
     */
    public function sendNewMessage($chatId, $messageData)
    {
        if (!$this->enabled) {
            return false;
        }

        $data = [
            'type' => 'new_message',
            'chatId' => $chatId,
            'message' => [
                'id' => $messageData['id'],
                'senderId' => $messageData['sender_id'],
                'senderName' => $messageData['sender_name'],
                'text' => $messageData['text'],
                'createdAt' => $messageData['created_at'],
                'isEdited' => $messageData['is_edited'] ?? false,
                'hasAttachments' => $messageData['has_attachments'] ?? false,
                'replyToMessageId' => $messageData['reply_to_message_id'] ?? null,
                'attachments' => $messageData['attachments'] ?? []
            ]
        ];

        return $this->sendNotificationSafely($data, 'Failed to send new message notification');
    }

    /**
     * Send read status update notification
     * Failsafe - never breaks app flow if WebSocket is down
     */
    public function sendReadStatusUpdate($chatId, $userId)
    {
        if (!$this->enabled) {
            return false;
        }

        $data = [
            'type' => 'message_read',
            'chatId' => $chatId,
            'userId' => $userId
        ];

        return $this->sendNotificationSafely($data, 'Failed to send read status notification');
    }

    /**
     * Safely send notification with error handling and retries
     * Never throws exceptions - always returns boolean
     */
    protected function sendNotificationSafely($data, $errorContext = 'WebSocket notification failed')
    {
        $attempts = 0;
        $maxAttempts = $this->retryCount + 1;

        while ($attempts < $maxAttempts) {
            $attempts++;

            try {
                $result = $this->sendNotification($data);

                if ($result) {
                    return true;
                }

            } catch (\Exception $e) {
                // Log error but continue trying
                if ($this->logErrors && $attempts === $maxAttempts) {
                    Yii::warning("$errorContext: " . $e->getMessage(), __METHOD__);
                }
            }

            // Wait before retry (except on last attempt)
            if ($attempts < $maxAttempts) {
                usleep(100000); // 0.1 second delay
            }
        }

        return false;
    }

    /**
     * Send notification to WebSocket server via HTTP
     */
    protected function sendNotification($data)
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->serverUrl . '/notify',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Internal-Request: true'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_NOSIGNAL => 1,
            CURLOPT_FAILONERROR => false
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("cURL error: {$error}");
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \Exception("HTTP error {$httpCode}");
        }

        $response = json_decode($result, true);
        if (!$response || !isset($response['success'])) {
            throw new \Exception("Invalid response format");
        }

        return $response['success'] === true;
    }

    /**
     * Check if WebSocket server is running
     */
    public function isServerRunning()
    {
        if (!$this->enabled) {
            return false;
        }

        try {
            $ch = curl_init();

            curl_setopt_array($ch, [
                CURLOPT_URL => $this->serverUrl . '/health',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->connectTimeout,
                CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
                CURLOPT_NOSIGNAL => 1,
                CURLOPT_FAILONERROR => false
            ]);

            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                return false;
            }

            return $httpCode > 0;

        } catch (\Exception $e) {
            return false;
        }
    }
}