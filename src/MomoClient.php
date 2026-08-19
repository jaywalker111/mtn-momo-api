<?php
declare(strict_types=1);

/*
 * MTN MoMo Collections API client.
 *
 * IMPORTANT:
 * Credentials come from .env.
 * Do NOT hard-code your MTN API key/subscription key here.
 */
final class MomoClient {
    private array $config;
    private ?string $token = null;
    private int $tokenExpiresAt = 0;

    public function __construct(array $config) {
        $this->config = $config;
    }

    private function request(string $method, string $path, array $headers = [], ?string $body = null, bool $withAuth = false): array {
        $url = $this->config['base_url'] . $path;

        if ($withAuth) {
            $headers[] = 'Authorization: Bearer ' . $this->getAccessToken();
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,

            // DO NOT set these to false in production.
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($ch);
        if ($responseBody === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('MTN cURL error: ' . $error);
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $status,
            'body' => $responseBody,
            'json' => json_decode($responseBody, true),
        ];
    }

    public function getAccessToken(): string {
        if ($this->token !== null && time() < ($this->tokenExpiresAt - 30)) {
            return $this->token;
        }

        // API User + API Key are used as HTTP Basic credentials.
        $credentials = base64_encode($this->config['api_user'] . ':' . $this->config['api_key']);

        $response = $this->request(
            'POST',
            '/collection/token/',
            [
                'Authorization: Basic ' . $credentials,

                // >>> CHANGE THIS IN .env, NOT HERE <<<
                'Ocp-Apim-Subscription-Key: ' . $this->config['subscription_key'],

                'Content-Type: application/x-www-form-urlencoded',
            ],
            'grant_type=client_credentials'
        );

        if ($response['status'] !== 200 || empty($response['json']['access_token'])) {
            throw new RuntimeException(
                'Unable to obtain MTN access token. HTTP ' . $response['status'] .
                ' Response: ' . substr($response['body'], 0, 500)
            );
        }

        $this->token = $response['json']['access_token'];
        $this->tokenExpiresAt = time() + (int)($response['json']['expires_in'] ?? 3600);

        return $this->token;
    }

    public function requestToPay(string $referenceId, string $amount, string $currency, string $payerMsisdn, string $externalId): array {
        /*
         * >>> OPTIONAL CHANGE: payment messages <<<
         * Example:
         * 'payerMessage' => 'School fees ' . $externalId,
         */
        $payload = [
            'amount' => $amount,
            'currency' => $currency,
            'externalId' => $externalId,
            'payer' => [
                'partyIdType' => $this->config['party_id_type'],
                'partyId' => $payerMsisdn,
            ],
            'payerMessage' => 'Payment for order ' . $externalId,
            'payeeNote' => 'Payment received',
        ];

        $response = $this->request(
            'POST',
            '/collection/v1_0/requesttopay',
            [
                'Authorization: Bearer ' . $this->getAccessToken(),
                'X-Reference-Id: ' . $referenceId,
                'X-Target-Environment: ' . $this->config['target_environment'],

                // >>> CHANGE THIS IN .env, NOT HERE <<<
                'Ocp-Apim-Subscription-Key: ' . $this->config['subscription_key'],

                'Content-Type: application/json',

                // >>> CHANGE THIS IN .env, NOT HERE <<<
                'X-Callback-Url: ' . $this->config['callback_url'],
            ],
            json_encode($payload, JSON_UNESCAPED_SLASHES)
        );

        return [
            'http_status' => $response['status'],
            'payload' => $payload,
            'raw' => $response['body'],
            'json' => $response['json'],
        ];
    }

    public function getPaymentStatus(string $referenceId): array {
        /*
         * Used by status.php, callback.php and cron/poll_pending.php.
         * Never rely only on the browser redirect to determine payment.
         */
        $response = $this->request(
            'GET',
            '/collection/v1_0/requesttopay/' . rawurlencode($referenceId),
            [
                'X-Target-Environment: ' . $this->config['target_environment'],
                'Ocp-Apim-Subscription-Key: ' . $this->config['subscription_key'],
            ],
            null,
            true
        );

        return [
            'http_status' => $response['status'],
            'raw' => $response['body'],
            'json' => $response['json'],
        ];
    }
}
