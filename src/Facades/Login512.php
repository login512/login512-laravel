<?php

declare(strict_types=1);

namespace Login512\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array verifyToken(string $token, ?string $expectedNonce = null)
 * @method static string generateNonce()
 * @method static array createEnrollmentToken(string $externalUserId, ?string $displayName = null, int $ttlSeconds = 120)
 * @method static array trustDevice(string $deviceRef)
 *
 * @see \Login512\Laravel\Login512Client
 */
class Login512 extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'login512';
    }
}
