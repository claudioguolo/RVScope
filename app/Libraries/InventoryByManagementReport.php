<?php

namespace App\Libraries;

class InventoryByManagementReport
{
    public function group(array $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $management = trim((string) ($row['gerencia'] ?? ''));
            if ($management === '') {
                $management = 'Sem registro';
            }

            $row['gerencia'] = $management;
            $groups[$management][] = $row;
        }

        uksort($groups, static function (string $left, string $right): int {
            if ($left === $right) {
                return 0;
            }
            if ($left === 'Sem registro') {
                return 1;
            }
            if ($right === 'Sem registro') {
                return -1;
            }

            return strcasecmp($left, $right);
        });

        foreach ($groups as &$items) {
            usort($items, static fn (array $left, array $right): int => strcasecmp(
                (string) ($left['vm'] ?? ''),
                (string) ($right['vm'] ?? '')
            ));
        }
        unset($items);

        return $groups;
    }
}
