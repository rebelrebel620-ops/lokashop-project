<?php
namespace App\Services;

use App\Models\User;

/**
 * Stateless, signed API tokens for the rider mobile app.
 *
 * Token format: "<userId>.<expiresAtUnix>.<hmac>"
 * - No database change needed: the token is verified with APP_KEY.
 * - The signature also covers the user's password hash, so changing a
 *   password instantly invalidates every token issued before it.
 * - Account status (approved / active / role) is re-checked on every request
 *   by the ApiRider middleware, so deactivating a rider locks them out at once.
 */
class ApiToken
{
    public const TTL = 60 * 60 * 24 * 30; // 30 days

    public static function issue(User $user): array
    {
        $expires = time() + self::TTL;
        $payload = $user->id . '.' . $expires;
        return [$payload . '.' . self::sign($payload, (string) $user->password), $expires];
    }

    public static function user(?string $token): ?User
    {
        if (!$token) return null;
        $parts = explode('.', $token);
        if (count($parts) !== 3 || !ctype_digit($parts[0]) || !ctype_digit($parts[1])) return null;
        if ((int) $parts[1] < time()) return null;

        $user = User::find((int) $parts[0]);
        if (!$user) return null;

        $expected = self::sign($parts[0] . '.' . $parts[1], (string) $user->password);
        return hash_equals($expected, $parts[2]) ? $user : null;
    }

    private static function sign(string $payload, string $passwordHash): string
    {
        $key = (string) config('app.key');
        abort_if($key === '', 500, 'APP_KEY is not set. Run: php artisan key:generate');
        return hash_hmac('sha256', $payload, $key . substr(hash('sha256', $passwordHash), 0, 32));
    }
}
