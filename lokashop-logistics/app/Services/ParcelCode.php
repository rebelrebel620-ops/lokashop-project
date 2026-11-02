<?php
namespace App\Services;

class ParcelCode
{
    /** Read either the printed code or the URL encoded in an existing label. */
    public static function parse(string $raw): string
    {
        $raw = trim(preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $raw) ?? $raw);
        if (preg_match('~^https?://~i', $raw)) {
            $parts = parse_url($raw) ?: [];
            parse_str($parts['query'] ?? '', $query);
            if (isset($query['code']) && is_string($query['code'])) return self::normalize($query['code']);
            if (preg_match('~/track/([^/?#]+)~i', $parts['path'] ?? '', $match)) return self::normalize(rawurldecode($match[1]));
            return '';
        }
        return self::normalize($raw);
    }
    private static function normalize(string $code):string {
        $code=trim(preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u','',$code)??$code);
        return preg_match('/^LKS-[A-Z0-9]+$/i',$code)?strtoupper($code):$code;
    }
}

