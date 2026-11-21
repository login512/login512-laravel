<?php

declare(strict_types=1);

namespace Login512;

/**
 * Login512 — Official PHP Server SDK
 * Implements Section 22 of blueprint512.md.
 * Requires PHP >= 8.0, ext-curl, ext-json.
 */
class Login512
{
    public const DEFAULT_ENDPOINT = 'https://api.login512.com';

    /**
     * Generates a cryptographically secure 32-byte nonce encoded as base64url.
     * Store this nonce in the user session prior to launching face authentication.
     */
    public static function generateNonce(): string
    {
        $bytes = random_bytes(32);
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /**
     * Verifies an ML-DSA-65 post-quantum L512 authentication token online via Login512 API.
     *
     * @param string $secretKey Tenant secret API key (starts with 'l512_sk_')
     * @param string $token L512 token returned by frontend SDK onSuccess callback
     * @param string|null $expectedNonce The nonce generated for this session to prevent replay
     * @param string $endpoint Optional API base URL override
     * @return array{valid: bool, claims: array|null, error: string|null, session_allowed: bool, step_up_required: bool}
     */
    public static function verifyToken(
        string $secretKey,
        string $token,
        ?string $expectedNonce = null,
        string $endpoint = self::DEFAULT_ENDPOINT
    ): array {
        if (!str_starts_with($secretKey, 'l512_sk_')) {
            return [
                'valid' => false,
                'claims' => null,
                'error' => 'Invalid secretKey: must start with l512_sk_',
                'session_allowed' => false,
                'step_up_required' => false,
            ];
        }

        if (empty($token)) {
            return [
                'valid' => false,
                'claims' => null,
                'error' => 'Missing or empty token',
                'session_allowed' => false,
                'step_up_required' => false,
            ];
        }

        $url = rtrim($endpoint, '/') . '/v1/token/verify';
        $payload = ['token' => $token];
        if ($expectedNonce !== null) {
            $payload['expected_nonce'] = $expectedNonce;
        }

        $res = self::request('POST', $url, $secretKey, $payload);
        if (!$res['success']) {
            return [
                'valid' => false,
                'claims' => null,
                'error' => $res['error'] ?? 'Verification failed',
                'session_allowed' => false,
                'step_up_required' => false,
            ];
        }

        $data = $res['data'] ?? [];
        $valid = !empty($data['valid']);
        $stepUpRequired = !empty($data['step_up_required']);
        $claims = $data['claims'] ?? null;
        $sessionAllowed = $valid && !$stepUpRequired;

        return [
            'valid' => $valid,
            'claims' => $claims,
            'error' => $data['error'] ?? null,
            'session_allowed' => $sessionAllowed,
            'step_up_required' => $stepUpRequired,
        ];
    }

    /**
     * Creates an authorized enrollment token for linking biometric login to an existing user account.
     *
     * @param string $secretKey Tenant secret API key (starts with 'l512_sk_')
     * @param string $externalUserId Your system's unique user identifier
     * @param string|null $displayName Human readable name for the user
     * @param int $ttlSeconds Validity duration in seconds (60-900)
     * @param string $endpoint Optional API base URL override
     * @return array API response containing token and expiration details
     * @throws \RuntimeException
     */
    public static function createEnrollmentToken(
        string $secretKey,
        string $externalUserId,
        ?string $displayName = null,
        int $ttlSeconds = 120,
        string $endpoint = self::DEFAULT_ENDPOINT
    ): array {
        if (!str_starts_with($secretKey, 'l512_sk_')) {
            throw new \InvalidArgumentException('Invalid secretKey: must start with l512_sk_');
        }
        if (empty($externalUserId)) {
            throw new \InvalidArgumentException('externalUserId is required');
        }

        $url = rtrim($endpoint, '/') . '/v1/enrollment-tokens';
        $payload = [
            'external_user_id' => $externalUserId,
            'ttl_seconds' => $ttlSeconds,
        ];
        if ($displayName !== null) {
            $payload['display_name'] = $displayName;
        }

        $res = self::request('POST', $url, $secretKey, $payload);
        if (!$res['success']) {
            throw new \RuntimeException('Failed to create enrollment token: ' . ($res['error'] ?? 'Unknown error'));
        }

        return $res['data'];
    }

    /**
     * Registers an unrecognized device as trusted after customer completes step-up verification.
     *
     * @param string $secretKey Tenant secret API key (starts with 'l512_sk_')
     * @param string $deviceRef Device reference token (claims.did from token verification)
     * @param string $endpoint Optional API base URL override
     * @return array API response
     * @throws \RuntimeException
     */
    public static function trustDevice(
        string $secretKey,
        string $deviceRef,
        string $endpoint = self::DEFAULT_ENDPOINT
    ): array {
        if (!str_starts_with($secretKey, 'l512_sk_')) {
            throw new \InvalidArgumentException('Invalid secretKey: must start with l512_sk_');
        }
        if (empty($deviceRef)) {
            throw new \InvalidArgumentException('deviceRef is required');
        }

        $url = rtrim($endpoint, '/') . '/v1/devices/trust';
        $payload = ['device_ref' => $deviceRef];

        $res = self::request('POST', $url, $secretKey, $payload);
        if (!$res['success']) {
            throw new \RuntimeException('Failed to trust device: ' . ($res['error'] ?? 'Unknown error'));
        }

        return $res['data'];
    }

    /**
     * Helper to execute HTTP requests via cURL.
     */
    private static function request(string $method, string $url, string $secretKey, array $payload): array
    {
        $ch = curl_init($url);
        $jsonPayload = json_encode($payload);

        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $secretKey,
            'User-Agent: login512-php-sdk/1.0',
        ];

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($rawResponse === false) {
            return ['success' => false, 'error' => 'cURL error: ' . $curlErr, 'code' => 0];
        }

        $data = json_decode($rawResponse, true);
        if ($httpCode >= 400) {
            $msg = is_array($data) ? ($data['message'] ?? $data['error'] ?? "HTTP {$httpCode}") : "HTTP {$httpCode}";
            return ['success' => false, 'error' => $msg, 'code' => $httpCode, 'data' => $data];
        }

        return ['success' => true, 'data' => $data, 'code' => $httpCode];
    }
}
