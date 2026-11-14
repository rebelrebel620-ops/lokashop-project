<?php

namespace App\Services;

use Illuminate\Contracts\Support\Arrayable;

/** Keep credentials out of audit records, including nested query-builder rows. */
class AuditData
{
    public static function redact(mixed $value): mixed
    {
        if (is_object($value)) {
            $value = $value instanceof Arrayable ? $value->toArray() : get_object_vars($value);
        }
        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $name = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $key));
            $sensitive = in_array($name, ['otp', 'code', 'hash', 'appkey', 'apikey', 'privatekey', 'activationhash', 'codehash'], true)
                || preg_match('/password|token|secret|credential|authorization|cookie|otphash/', $name);
            $value[$key] = $sensitive ? '[REDACTED]' : self::redact($item);
        }

        return $value;
    }
}
