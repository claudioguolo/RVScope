<?php

namespace App\Libraries;

final class OperatingSystemInclusionPolicy
{
    private const DEFAULT_IGNORED_PREFIXES = ['Microsoft', 'VMware', 'Forti'];

    public function included(
        string $powerState,
        string $rawOperatingSystem,
        string $normalizedOperatingSystem,
        array $policies,
    ): bool {
        if ($powerState !== 'poweredOn' || $normalizedOperatingSystem === '') {
            return false;
        }

        return ! ($policies[$rawOperatingSystem] ?? $this->ignoredByDefault($rawOperatingSystem));
    }

    public function ignoredByDefault(string $operatingSystem): bool
    {
        foreach (self::DEFAULT_IGNORED_PREFIXES as $prefix) {
            if (str_starts_with($operatingSystem, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
