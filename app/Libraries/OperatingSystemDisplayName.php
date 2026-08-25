<?php

namespace App\Libraries;

final class OperatingSystemDisplayName
{
    public function clean(string $value): string
    {
        $cleaned = preg_replace('/\s*\(64-bits?\)/iu', '', trim($value));

        return trim($cleaned ?? $value);
    }
}
