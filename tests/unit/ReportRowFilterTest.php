<?php

namespace Tests\Unit;

use App\Libraries\ReportRowFilter;
use PHPUnit\Framework\TestCase;

final class ReportRowFilterTest extends TestCase
{
    public function testFiltersRowsWithoutControllerOrHttpDependencies(): void
    {
        $rows = [
            ['vm' => 'vm-1', 'info' => ['gerencia' => 'Infra', 'app' => '1', 'leg' => '0', 'mig' => '1']],
            ['vm' => 'vm-2', 'info' => ['gerencia' => 'Aplicações', 'app' => '0', 'leg' => '1', 'mig' => '0']],
        ];
        $filter = new ReportRowFilter();

        self::assertSame(['vm-1'], array_column($filter->byManagementUnit($rows, 'infra'), 'vm'));
        self::assertSame(['vm-1'], array_column($filter->appliances($rows), 'vm'));
        self::assertSame(['vm-2'], array_column($filter->legacy($rows), 'vm'));
        self::assertSame(['vm-1'], array_column($filter->migrable($rows), 'vm'));
    }
}
