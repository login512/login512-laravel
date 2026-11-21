<?php

declare(strict_types=1);

namespace Login512\Laravel;

use Login512\Login512;

/**
 * Container binding behind the Login512 facade. Every call goes to the Login512 API with your SECRET key.
 */
class Login512Client
{
    public function __construct(
        private readonly string $secretKey,
        private readonly string $endpoint = Login512::DEFAULT_ENDPOINT,
    ) {
    }

    /**
     * Returns ['valid', 'claims', 'error', 'session_allowed', 'step_up_required'].
     * Log a user in ONLY when 'session_allowed' is true ('valid' alone can still mean "step-up required").
     */
    public function verifyToken(string $token, ?string $expectedNonce = null): array
    {
        return Login512::verifyToken($this->secretKey, $token, $expectedNonce, $this->endpoint);
    }

    public function generateNonce(): string
    {
        return Login512::generateNonce();
    }

    public function createEnrollmentToken(string $externalUserId, ?string $displayName = null, int $ttlSeconds = 120): array
    {
        return Login512::createEnrollmentToken($this->secretKey, $externalUserId, $displayName, $ttlSeconds, $this->endpoint);
    }

    /** Marks a device as trusted (pass claims.did from a verified token). */
    public function trustDevice(string $deviceRef): array
    {
        return Login512::trustDevice($this->secretKey, $deviceRef, $this->endpoint);
    }
}
