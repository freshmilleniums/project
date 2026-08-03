<?php

namespace common\services;

use Yii;
use yii\base\Exception;
use common\models\UserCorporateEmail;
use common\models\EmailAccount;
use common\models\CorporateEmailMessage;
use common\models\ExternalEmailMessage;
use common\models\CorporateEmailAttachment;
use common\models\ExternalEmailAttachment;

class EmailSyncService
{
    /**
     * Sync corporate email account
     *
     * @param int $accountId
     * @throws Exception
     */
    public function syncCorporateAccount($accountId)
    {
        $account = UserCorporateEmail::findOne($accountId);

        if (!$account) {
            throw new Exception("Corporate email account {$accountId} not found.");
        }

        $imapHost       = Yii::$app->params['corporateImapHost'] ?? null;

        $companyId = Yii::$app->params['company_id'] ?? null;
        if ($companyId) {
            $company = \common\models\Companies::findOne($companyId);
            if ($company && !empty($company->email_domain)) {
                $imapHost = 'imap.' . $company->email_domain;
            }
        }

        $imapPort       = Yii::$app->params['corporateImapPort'] ?? 993;
        $imapEncryption = Yii::$app->params['corporateImapEncryption'] ?? 'ssl';

        if (!$imapHost) {
            throw new Exception('Corporate IMAP host not configured.');
        }

        $password = $account->getDecryptedPassword();

        if (!$password) {
            throw new Exception('Failed to decrypt corporate email password.');
        }

        $this->syncViaIMAP(
            $imapHost, $imapPort, $imapEncryption,
            $account->email, $password,
            'corporate', $account
        );
    }

    /**
     * Sync external email account
     *
     * @param int $accountId
     * @throws Exception
     */
    public function syncExternalAccount($accountId)
    {
        $account = EmailAccount::findOne($accountId);

        if (!$account) {
            throw new Exception("External email account {$accountId} not found.");
        }

        $encryptionMap  = ['SSL' => 'ssl', 'TLS' => 'tls'];
        $imapEncryption = $encryptionMap[$account->getImapEncryptionName()] ?? 'ssl';

        $password = $account->getDecryptedPassword();

        if (!$password) {
            throw new Exception('Failed to decrypt external email password.');
        }

        $this->syncViaIMAP(
            $account->imap_host, $account->imap_port, $imapEncryption,
            $account->username, $password,
            'external', $account
        );
    }

    /**
     * Sync messages via IMAP using UID-based fetching
     *
     * @param string $host
     * @param int $port
     * @param string $encryption
     * @param string $username
     * @param string $password
     * @param string $accountType
     * @param UserCorporateEmail|EmailAccount $account
     * @throws Exception
     */
    private function syncViaIMAP($host, $port, $encryption, $username, $password, $accountType, $account)
    {
        $encryptionPrefix = ($encryption === 'ssl') ? 'ssl' : 'tls';
        $mailbox = '{' . $host . ':' . $port . '/imap/' . $encryptionPrefix . '}INBOX';

        $imap = @imap_open($mailbox, $username, $password);

        if (!$imap) {
            $error = imap_last_error();
            Yii::error("IMAP connection failed for {$accountType} account {$account->id}: {$error}", 'email');
            throw new Exception("Failed to connect to IMAP server: {$error}");
        }

        // Check UIDVALIDITY — reset last_uid if server invalidated UIDs
        $status = imap_status($imap, $mailbox, SA_UIDVALIDITY);
        if ($status) {
            $uidValidityKey    = "uidvalidity_{$accountType}_{$account->id}";
            $storedUidValidity = Yii::$app->cache->get($uidValidityKey);

            if ($storedUidValidity && $storedUidValidity != $status->uidvalidity) {
                Yii::warning("UIDVALIDITY changed for {$accountType} account {$account->id}, resetting last_uid", 'email');
                $account->last_uid = 0;
            }

            Yii::$app->cache->set($uidValidityKey, $status->uidvalidity, 86400 * 30);
        }

        $lastUid = $account->last_uid ?? 0;

        // Fetch only messages newer than last known UID
        if ($lastUid > 0) {
            $emailUids = @imap_search($imap, 'ALL', SE_UID);
            if ($emailUids) {
                $emailUids = array_filter($emailUids, fn($uid) => $uid > $lastUid);
            }
        } else {
            // First sync — fetch last 100 messages only
            $emailUids = @imap_search($imap, 'ALL', SE_UID);
            if ($emailUids) {
                $emailUids = array_slice(array_reverse($emailUids), 0, 100);
            }
        }

        if (!$emailUids) {
            $account->last_sync_at = time();
            $account->save(false, ['last_sync_at']);
            imap_close($imap);
            return;
        }

        // Process in ascending UID order
        sort($emailUids);

        $syncedCount = 0;
        $maxUid      = $lastUid;

        foreach ($emailUids as $uid) {
            try {
                $synced = $this->syncSingleMessage($imap, $uid, $accountType, $account->id);
                if ($synced) {
                    $syncedCount++;
                }
                // Track max UID even for duplicates
                if ($uid > $maxUid) {
                    $maxUid = $uid;
                }
            } catch (\Exception $e) {
                Yii::error("Failed to sync message UID {$uid}: " . $e->getMessage(), 'email');
            }
        }

        // Save sync progress
        $account->last_uid     = $maxUid;
        $account->last_sync_at = time();
        $account->save(false, ['last_uid', 'last_sync_at']);

        imap_close($imap);

        Yii::info("Synced {$syncedCount} new messages for {$accountType} account {$account->id} (last_uid: {$maxUid})", 'email');
    }

    /**
     * Sync single message by UID
     *
     * @param resource $imap
     * @param int $uid
     * @param string $accountType
     * @param int $accountId
     * @return bool True if new message was saved
     */
    private function syncSingleMessage($imap, $uid, $accountType, $accountId)
    {
        $msgno = imap_msgno($imap, $uid);

        if (!$msgno) {
            return false;
        }

        $header = @imap_headerinfo($imap, $msgno);

        if (!$header) {
            return false;
        }

        $messageId = $header->message_id ?? null;

        if (!$messageId) {
            $messageId = '<uid_' . $uid . '_' . $accountId . '_' . $accountType . '@localhost>';
        }

        // Skip duplicates
        if ($accountType == 'corporate') {
            $exists = CorporateEmailMessage::find()->where(['message_id' => $messageId])->exists();
        } else {
            $exists = ExternalEmailMessage::find()->where(['message_id' => $messageId])->exists();
        }

        if ($exists) {
            return false;
        }

        $structure = imap_fetchstructure($imap, $msgno);
        $from      = $this->getEmailAddress($header->from ?? []);
        $to        = $this->getEmailAddresses($header->to ?? []);
        $cc        = $this->getEmailAddresses($header->cc ?? []);
        $subject   = $this->decodeHeader($header->subject ?? '(No Subject)');
        $body      = $this->getMessageBody($imap, $msgno, $structure);

        $receivedTimestamp = isset($header->date) ? strtotime($header->date) : time();

        if ($accountType == 'corporate') {
            $message                     = new CorporateEmailMessage();
            $message->corporate_email_id = $accountId;
            $message->is_read            = 0;
        } else {
            $message                  = new ExternalEmailMessage();
            $message->email_account_id = $accountId;
        }

        $message->message_id    = $messageId;
        $message->message_uid   = (string)$uid;
        $message->direction     = 'incoming';
        $message->folder        = 'INBOX';
        $message->from_email    = $from['email'];
        $message->from_name     = $from['name'];
        $message->to_emails     = json_encode($to);
        $message->cc_emails     = !empty($cc) ? json_encode($cc) : null;
        $message->subject       = $subject;
        $message->body_html     = $body['html'] ?? null;
        $message->body_text     = $body['text'] ?? strip_tags($body['html'] ?? '');
        $message->received_at   = $receivedTimestamp;

        $attachments            = $this->getAttachments($imap, $msgno, $structure);
        $message->has_attachments = !empty($attachments) ? 1 : 0;

        if (!$message->save()) {
            Yii::error('Failed to save message: ' . json_encode($message->errors), 'email');
            return false;
        }

        if (!empty($attachments)) {
            $this->saveIncomingAttachments($message, $attachments, $accountType);
        }

        return true;
    }

    /**
     * Get email address from IMAP header object
     */
    private function getEmailAddress($addresses)
    {
        if (empty($addresses)) {
            return ['email' => 'unknown@localhost', 'name' => 'Unknown'];
        }

        $address = is_array($addresses) ? $addresses[0] : $addresses;
        $email   = (isset($address->mailbox) && isset($address->host))
            ? $address->mailbox . '@' . $address->host
            : 'unknown@localhost';
        $name    = isset($address->personal) ? $this->decodeHeader($address->personal) : $email;

        return ['email' => $email, 'name' => $name];
    }

    /**
     * Get multiple email addresses from IMAP header
     */
    private function getEmailAddresses($addresses)
    {
        if (empty($addresses)) {
            return [];
        }

        $result = [];
        foreach ($addresses as $address) {
            if (isset($address->mailbox) && isset($address->host)) {
                $result[] = $address->mailbox . '@' . $address->host;
            }
        }

        return $result;
    }

    /**
     * Decode MIME-encoded header string to UTF-8
     */
    private function decodeHeader($text)
    {
        $decoded = imap_mime_header_decode($text);
        $result  = '';

        foreach ($decoded as $part) {
            $charset = ($part->charset === 'default') ? 'UTF-8' : $part->charset;
            $result .= mb_convert_encoding($part->text, 'UTF-8', $charset);
        }

        return $result;
    }

    /**
     * Get message body — handles nested multipart recursively
     */
    private function getMessageBody($imap, $msgno, $structure)
    {
        $body = ['html' => null, 'text' => null];

        if (!isset($structure->parts)) {
            // Single-part message
            $bodyText = imap_body($imap, $msgno);

            if ($structure->encoding == 3) {
                $bodyText = base64_decode($bodyText);
            } elseif ($structure->encoding == 4) {
                $bodyText = quoted_printable_decode($bodyText);
            }

            if (isset($structure->subtype) && strtolower($structure->subtype) == 'html') {
                $body['html'] = $bodyText;
            } else {
                $body['text'] = $bodyText;
            }

            return $body;
        }

        $this->parseBodyParts($imap, $msgno, $structure->parts, $body, '');

        return $body;
    }

    /**
     * Recursively parse body parts to extract HTML and plain text
     */
    private function parseBodyParts($imap, $msgno, $parts, &$body, $prefix)
    {
        foreach ($parts as $index => $part) {
            $partNumber = $prefix ? $prefix . '.' . ($index + 1) : (string)($index + 1);

            if ($part->type == 0) {
                // Text part
                $bodyPart = imap_fetchbody($imap, $msgno, $partNumber);

                if ($part->encoding == 3) {
                    $bodyPart = base64_decode($bodyPart);
                } elseif ($part->encoding == 4) {
                    $bodyPart = quoted_printable_decode($bodyPart);
                }

                // Detect charset from part parameters
                $charset = 'UTF-8';
                if (isset($part->parameters)) {
                    foreach ($part->parameters as $param) {
                        if (strtolower($param->attribute) == 'charset') {
                            $charset = $param->value;
                            break;
                        }
                    }
                }

                $bodyPart = mb_convert_encoding($bodyPart, 'UTF-8', $charset);

                if (isset($part->subtype)) {
                    if (strtolower($part->subtype) == 'html' && empty($body['html'])) {
                        $body['html'] = $bodyPart;
                    } elseif (strtolower($part->subtype) == 'plain' && empty($body['text'])) {
                        $body['text'] = $bodyPart;
                    }
                }
            } elseif ($part->type == 1 && isset($part->parts)) {
                // Multipart — recurse into nested parts
                $this->parseBodyParts($imap, $msgno, $part->parts, $body, $partNumber);
            }
        }
    }

    /**
     * Get attachments from message structure
     */
    private function getAttachments($imap, $msgno, $structure)
    {
        $attachments = [];

        if (!isset($structure->parts)) {
            return $attachments;
        }

        $this->extractAttachments($imap, $msgno, $structure->parts, $attachments, '');

        return $attachments;
    }

    /**
     * Recursively extract attachments from message parts
     */
    private function extractAttachments($imap, $msgno, $parts, &$attachments, $prefix)
    {
        foreach ($parts as $index => $part) {
            $partNumber   = $prefix ? $prefix . '.' . ($index + 1) : (string)($index + 1);
            $isAttachment = false;
            $filename     = null;

            if (isset($part->disposition) && strtolower($part->disposition) == 'attachment') {
                $isAttachment = true;
            }

            if (isset($part->dparameters)) {
                foreach ($part->dparameters as $param) {
                    if (strtolower($param->attribute) == 'filename') {
                        $filename     = $this->decodeHeader($param->value);
                        $isAttachment = true;
                        break;
                    }
                }
            }

            if (!$filename && isset($part->parameters)) {
                foreach ($part->parameters as $param) {
                    if (strtolower($param->attribute) == 'name') {
                        $filename     = $this->decodeHeader($param->value);
                        $isAttachment = true;
                        break;
                    }
                }
            }

            if ($isAttachment && $filename) {
                $fileData = imap_fetchbody($imap, $msgno, $partNumber);

                if ($part->encoding == 3) {
                    $fileData = base64_decode($fileData);
                } elseif ($part->encoding == 4) {
                    $fileData = quoted_printable_decode($fileData);
                }

                $mimeType = 'application/octet-stream';
                if (isset($part->subtype)) {
                    $typeMap  = [0 => 'text', 1 => 'multipart', 2 => 'message', 3 => 'application', 4 => 'audio', 5 => 'image', 6 => 'video'];
                    $mimeType = ($typeMap[$part->type] ?? 'application') . '/' . strtolower($part->subtype);
                }

                $attachments[] = [
                    'filename'  => $filename,
                    'data'      => $fileData,
                    'size'      => strlen($fileData),
                    'mime_type' => $mimeType,
                ];
            }

            // Recurse into multipart
            if ($part->type == 1 && isset($part->parts)) {
                $this->extractAttachments($imap, $msgno, $part->parts, $attachments, $partNumber);
            }
        }
    }

    /**
     * Save incoming email attachments to disk
     */
    private function saveIncomingAttachments($message, $attachments, $accountType)
    {
        $uploadDir = Yii::getAlias('@backend/web/uploads/email-attachments/' . date('Y/m'));

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        foreach ($attachments as $attachment) {
            $filename = uniqid() . '_' . $attachment['filename'];
            $filePath = $uploadDir . '/' . $filename;

            if (file_put_contents($filePath, $attachment['data'])) {
                $attachmentModel = $accountType == 'corporate'
                    ? new CorporateEmailAttachment()
                    : new ExternalEmailAttachment();

                $attachmentModel->message_id   = $message->id;
                $attachmentModel->filename     = $attachment['filename'];
                $attachmentModel->content_type = $attachment['mime_type'];
                $attachmentModel->size         = $attachment['size'];
                $attachmentModel->file_path    = $filePath;

                if (!$attachmentModel->save()) {
                    Yii::error('Failed to save attachment: ' . json_encode($attachmentModel->errors), 'email');
                }
            }
        }
    }
}