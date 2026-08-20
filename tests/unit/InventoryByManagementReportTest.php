<?php

namespace Tests\Unit;

use App\Libraries\InventoryByManagementReport;
use PHPUnit\Framework\TestCase;

final class InventoryByManagementReportTest extends TestCase
{
    public function testGroupsAndSortsHostsByManagementUnit(): void
    {
        $rows = [
            ['vm' => 'vm-z', 'gerencia' => ''],
            ['vm' => 'vm-b', 'gerencia' => 'Infraestrutura'],
            ['vm' => 'vm-a', 'gerencia' => 'Infraestrutura'],
            ['vm' => 'vm-c', 'gerencia' => 'Aplicações'],
        ];

        $groups = (new InventoryByManagementReport())->group($rows);

        self::assertSame(['Aplicações', 'Infraestrutura', 'Sem registro'], array_keys($groups));
        self::assertSame(['vm-a', 'vm-b'], array_column($groups['Infraestrutura'], 'vm'));
        self::assertSame('Sem registro', $groups['Sem registro'][0]['gerencia']);
    }
}
