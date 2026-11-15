<?php

namespace App\Services;

use RuntimeException;

/** Update security settings without regenerating keys or replacing credentials. */
class SecurityConfig
{
    public static function apply(string $path, bool $production = false, ?string $url = null): void
    {
        $changes = ['APP_DEBUG' => 'false'];
        if ($production) {
            $parts = $url !== null ? parse_url($url) : false;
            if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
                || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
                || !filter_var($url, FILTER_VALIDATE_URL)) {
                throw new RuntimeException('Production setup needs --url=https://YOUR_WEBSITE with no credentials, query or fragment.');
            }
            $changes += ['APP_ENV' => 'production', 'APP_URL' => rtrim($url, '/'), 'SESSION_SECURE_COOKIE' => 'true'];
        } elseif ($url !== null) {
            throw new RuntimeException('Use --url together with --production, or edit APP_URL for local development.');
        }
        $contents = @file_get_contents($path);
        if ($contents === false || !is_writable($path)) {
            throw new RuntimeException('The existing .env must be readable and writable. No new credentials were generated.');
        }
        $newline = str_contains($contents, "\r\n") ? "\r\n" : "\n";
        foreach ($changes as $key => $value) {
            $pattern = '/^[\t ]*(?:export[\t ]+)?'.preg_quote($key, '/').'[\t ]*=[^\r\n]*/m';
            if (preg_match($pattern, $contents)) {
                $contents = preg_replace_callback($pattern, fn () => $key.'='.$value, $contents);
            } else {
                $contents = rtrim($contents, "\r\n").$newline.$key.'='.$value.$newline;
            }
        }
        $temporary = tempnam(dirname($path), '.security-env-');
        if ($temporary === false) {
            throw new RuntimeException('Cannot prepare the configuration update.');
        }
        try {
            $mode = fileperms($path);
            if ($mode !== false) {
                chmod($temporary, $mode & 0777);
            }
            if (file_put_contents($temporary, $contents, LOCK_EX) !== strlen($contents) || !rename($temporary, $path)) {
                throw new RuntimeException('Cannot save the configuration update. Check file permissions.');
            }
        } finally {
            if (file_exists($temporary)) {
                unlink($temporary);
            }
        }
    }
}
