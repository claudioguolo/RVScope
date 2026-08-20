<?php

namespace App\Libraries;

final class RvtoolsOsFallbackResolver
{
    public function apply(array $inventory, array $lastKnownOsByVm): array
    {
        foreach ($inventory as $vm => &$snapshot) {
            $isPoweredOn = (string) ($snapshot['power_state'] ?? '') === 'poweredOn';
            $currentOs = trim((string) ($snapshot['os_name'] ?? ''));
            $lastKnownOs = trim((string) ($lastKnownOsByVm[$vm] ?? ''));

            if ($isPoweredOn && $currentOs === '' && $lastKnownOs !== '') {
                $snapshot['os_name'] = $lastKnownOs;
                $snapshot['included_in_reports'] = true;
            }
        }
        unset($snapshot);

        return $inventory;
    }

    public function summarize(array $inventory): array
    {
        $summary = [];
        foreach ($inventory as $snapshot) {
            if (! (bool) ($snapshot['included_in_reports'] ?? false)) {
                continue;
            }

            $osName = trim((string) ($snapshot['os_name'] ?? ''));
            if ($osName !== '') {
                $summary[$osName] = ($summary[$osName] ?? 0) + 1;
            }
        }

        return $summary;
    }
}
