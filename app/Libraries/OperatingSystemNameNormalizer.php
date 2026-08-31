<?php

namespace App\Libraries;

final class OperatingSystemNameNormalizer
{
    public function __construct(private int $maximumLength = 27)
    {
    }

    public function normalize(string $operatingSystem): string
    {
        if (str_starts_with($operatingSystem, 'CentOS')) {
            return 'CentOS';
        }
        if (str_starts_with($operatingSystem, 'Other')
            || str_starts_with($operatingSystem, 'SUSE ')
            || str_starts_with($operatingSystem, 'FreeB')) {
            return 'Other';
        }

        $clean = trim(str_replace(' (64-bit)', '', $operatingSystem));

        return strlen($clean) > $this->maximumLength
            ? substr($clean, 0, $this->maximumLength)
            : $clean;
    }
}
