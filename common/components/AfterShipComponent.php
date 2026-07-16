<?php

namespace common\components;

use Yii;
use yii\base\Component;
use yii\base\Exception;

class AfterShipComponent extends Component
{
    /**
     * @var string AfterShip API Key
     */
    public $apiKey;

    /**
     * @var string API Base URL
     */
    public $baseUrl = 'https://api.aftership.com/v4';

    /**
     * Component initialization
     */
    public function init()
    {
        parent::init();

        // Get API key from params
        if (!$this->apiKey) {
            $this->apiKey = Yii::$app->params['aftership']['apiKey'] ?? null;
        }

        if (!$this->apiKey) {
            throw new Exception('AfterShip API key is required');
        }
    }

    /**
     * Make HTTP request using cURL
     *
     * @param string $method HTTP method (GET, POST)
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array
     * @throws Exception
     */
    private function makeRequest($method, $endpoint, $data = [])
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

        $ch = curl_init();

        // Basic cURL options
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'aftership-api-key: ' . $this->apiKey,
            ],
        ]);

        // Set method specific options
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'GET') {
            if (!empty($data)) {
                $url .= '?' . http_build_query($data);
                curl_setopt($ch, CURLOPT_URL, $url);
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            throw new Exception('cURL Error: ' . $error);
        }

        $decodedResponse = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMsg = isset($decodedResponse['meta']['message'])
                ? $decodedResponse['meta']['message']
                : 'HTTP Error ' . $httpCode;
            throw new Exception('AfterShip API Error: ' . $httpCode . ' - ' . $errorMsg);
        }

        return [
            'data' => $decodedResponse,
            'isOk' => $httpCode >= 200 && $httpCode < 300,
            'statusCode' => $httpCode
        ];
    }

    /**
     * Create tracking for a package
     *
     * @param string $trackingNumber Tracking number
     * @param string|null $slug Courier code (e.g., 'ups', 'dhl')
     * @param array $additionalData Additional tracking data
     * @return array
     * @throws Exception
     */
    public function createTracking($trackingNumber, $slug = null, $additionalData = [])
    {
        $data = array_merge([
            'tracking_number' => $trackingNumber,
        ], $additionalData);

        if ($slug) {
            $data['slug'] = $slug;
        }

        try {
            $response = $this->makeRequest('POST', 'trackings', [
                'tracking' => $data
            ]);

            if ($response['isOk']) {
                return $response['data'];
            } else {
                throw new Exception('AfterShip API Error: ' . $response['statusCode']);
            }
        } catch (\Exception $e) {
            Yii::error('AfterShip API Error: ' . $e->getMessage(), __METHOD__);
            throw new Exception('Failed to create tracking: ' . $e->getMessage());
        }
    }

    /**
     * Get tracking status by tracking number
     *
     * @param string $trackingNumber Tracking number
     * @param string|null $slug Courier code
     * @return array|null
     * @throws Exception
     */
    public function getTrackingStatus($trackingNumber, $slug = null)
    {
        // If slug is not specified, try to detect it automatically
        if (!$slug) {
            try {
                $detectResult = $this->detectCourier($trackingNumber);
                if (!empty($detectResult['data']['couriers'])) {
                    $slug = $detectResult['data']['couriers'][0]['slug'];
                }
            } catch (\Exception $e) {
                // Ignore courier detection errors
            }
        }

        // Try different variants of ID formation
        $trackingIds = [];
        if ($slug) {
            $trackingIds[] = "{$slug}:{$trackingNumber}";
        }
        $trackingIds[] = $trackingNumber;

        // URL encoding for special characters
        $trackingIds[] = urlencode($trackingNumber);
        if ($slug) {
            $trackingIds[] = "{$slug}:" . urlencode($trackingNumber);
        }

        $lastError = null;

        foreach ($trackingIds as $trackingId) {
            try {
                Yii::info("Trying tracking ID: {$trackingId}", __METHOD__);

                $response = $this->makeRequest('GET', "trackings/{$trackingId}");

                if ($response['isOk']) {
                    return $response['data']['data']['tracking'] ?? null;
                } else if ($response['statusCode'] == 404) {
                    continue; // Try next ID
                } else {
                    $lastError = 'AfterShip API Error: ' . $response['statusCode'];
                    continue;
                }
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                continue;
            }
        }

        // If nothing worked, throw the last error
        Yii::error('AfterShip API Error: ' . $lastError, __METHOD__);
        throw new Exception('Failed to get tracking status: ' . $lastError);
    }

    /**
     * Get simplified tracking status information
     *
     * @param string $trackingNumber Tracking number
     * @param string|null $slug Courier code
     * @return array|null Array with main status information
     */
    public function getSimpleStatus($trackingNumber, $slug = null)
    {
        $tracking = $this->getTrackingStatus($trackingNumber, $slug);

        if (!$tracking) {
            return null;
        }

        return [
            'tracking_number' => $tracking['tracking_number'],
            'slug' => $tracking['slug'],
            'status' => $tracking['tag'],
            'status_text' => $this->getStatusText($tracking['tag']),
            'last_updated' => $tracking['updated_at'],
            'delivery_date' => $tracking['expected_delivery'] ?? null,
            'origin_country' => $tracking['origin_country_iso3'] ?? null,
            'destination_country' => $tracking['destination_country_iso3'] ?? null,
            'checkpoints_count' => count($tracking['checkpoints'] ?? []),
            'last_checkpoint' => !empty($tracking['checkpoints']) ? end($tracking['checkpoints']) : null,
        ];
    }

    /**
     * Get text description for status
     *
     * @param string $status Status code
     * @return string
     */
    private function getStatusText($status)
    {
        $statuses = [
            'Pending' => 'Pending',
            'InfoReceived' => 'Info Received',
            'InTransit' => 'In Transit',
            'OutForDelivery' => 'Out for Delivery',
            'AttemptFail' => 'Delivery Attempt Failed',
            'Delivered' => 'Delivered',
            'AvailableForPickup' => 'Available for Pickup',
            'Exception' => 'Exception',
            'Expired' => 'Expired'
        ];

        return $statuses[$status] ?? $status;
    }

    /**
     * Detect courier by tracking number
     *
     * @param string $trackingNumber Tracking number
     * @return array
     * @throws Exception
     */
    public function detectCourier($trackingNumber)
    {
        try {
            $response = $this->makeRequest('POST', 'couriers/detect', [
                'tracking' => [
                    'tracking_number' => $trackingNumber
                ]
            ]);

            if ($response['isOk']) {
                return $response['data'];
            } else {
                throw new Exception('AfterShip API Error: ' . $response['statusCode']);
            }
        } catch (\Exception $e) {
            Yii::error('AfterShip API Error: ' . $e->getMessage(), __METHOD__);
            throw new Exception('Failed to detect courier: ' . $e->getMessage());
        }
    }

    /**
     * Get list of all trackings
     *
     * @return array
     * @throws Exception
     */
    public function getAllTrackings()
    {
        try {
            $response = $this->makeRequest('GET', 'trackings');

            if ($response['isOk']) {
                return $response['data'];
            } else {
                throw new Exception('AfterShip API Error: ' . $response['statusCode']);
            }
        } catch (\Exception $e) {
            Yii::error('AfterShip API Error: ' . $e->getMessage(), __METHOD__);
            throw new Exception('Failed to get trackings list: ' . $e->getMessage());
        }
    }

    /**
     * Get tracking by internal ID
     *
     * @param string $id Internal tracking ID
     * @return array|null
     * @throws Exception
     */
    public function getTrackingById($id)
    {
        try {
            $response = $this->makeRequest('GET', "trackings/{$id}");

            if ($response['isOk']) {
                return $response['data']['data']['tracking'] ?? null;
            } else if ($response['statusCode'] == 404) {
                return null;
            } else {
                throw new Exception('AfterShip API Error: ' . $response['statusCode']);
            }
        } catch (\Exception $e) {
            Yii::error('AfterShip API Error: ' . $e->getMessage(), __METHOD__);
            throw new Exception('Failed to get tracking by ID: ' . $e->getMessage());
        }
    }

    /**
     * Get trackings by tracking numbers using search parameters
     *
     * @param string|array $trackingNumbers Tracking number(s) - string for single, array for multiple
     * @param string|null $slug Courier code
     * @param array $additionalParams Additional search parameters
     * @return array
     * @throws Exception
     */
    public function getTrackingsByNumbers($trackingNumbers, $slug = null, $additionalParams = [])
    {
        // Prepare search parameters
        $params = $additionalParams;

        // Add tracking numbers
        if (is_array($trackingNumbers)) {
            $params['tracking_numbers'] = implode(',', $trackingNumbers);
        } else {
            $params['tracking_numbers'] = $trackingNumbers;
        }

        // Add slug if specified
        if ($slug) {
            $params['slug'] = $slug;
        }

        try {
            $response = $this->makeRequest('GET', 'trackings', $params);

            if ($response['isOk']) {
                return $response['data'];
            } else {
                throw new Exception('AfterShip API Error: ' . $response['statusCode']);
            }
        } catch (\Exception $e) {
            Yii::error('AfterShip API Error: ' . $e->getMessage(), __METHOD__);
            throw new Exception('Failed to get trackings by numbers: ' . $e->getMessage());
        }
    }

    /**
     * Get single tracking by tracking number using search method
     * Alternative to getTrackingStatus method
     *
     * @param string $trackingNumber Tracking number
     * @param string|null $slug Courier code
     * @return array|null
     * @throws Exception
     */
    public function findTrackingByNumber($trackingNumber, $slug = null)
    {
        try {
            $result = $this->getTrackingsByNumbers($trackingNumber, $slug);

            if (!empty($result['data']['trackings'])) {
                // Return the first found tracking
                return $result['data']['trackings'][0];
            }

            return null;
        } catch (\Exception $e) {
            Yii::error('AfterShip API Error: ' . $e->getMessage(), __METHOD__);
            throw new Exception('Failed to find tracking by number: ' . $e->getMessage());
        }
    }

    /**
     * Search trackings with advanced filters
     *
     * @param array $filters Search filters
     * @return array
     * @throws Exception
     */
    public function searchTrackings($filters = [])
    {
        try {
            $response = $this->makeRequest('GET', 'trackings', $filters);

            if ($response['isOk']) {
                return $response['data'];
            } else {
                throw new Exception('AfterShip API Error: ' . $response['statusCode']);
            }
        } catch (\Exception $e) {
            Yii::error('AfterShip API Error: ' . $e->getMessage(), __METHOD__);
            throw new Exception('Failed to search trackings: ' . $e->getMessage());
        }
    }
}