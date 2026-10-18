<?php
namespace App\Services;

class ParcelCode
{
    /** Read either the printed code or the URL encoded in an existing label. */
    public static function parse(string $raw): string
    {
        $raw = trim($raw);
        if (preg_match('~^https?://~i', $raw)) {
            $parts = parse_url($raw) ?: [];
            parse_str($parts['query'] ?? '', $query);
            if (isset($query['code']) && is_string($query['code'])) return trim($query['code']);
            if (preg_match('~/track/([^/?#]+)~i', $parts['path'] ?? '', $match)) return trim(rawurldecode($match[1]));
            return '';
        }
        return $raw;
    }
}
