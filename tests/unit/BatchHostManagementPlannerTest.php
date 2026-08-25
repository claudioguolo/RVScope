<?php

namespace Tests\Unit;

use App\Libraries\BatchHostManagementPlanner;
use PHPUnit\Framework\TestCase;

final class BatchHostManagementPlannerTest extends TestCase
{
    public function testKeepsOnlyResponsiblesAllowedAtDestination(): void
    {
        $plan = (new BatchHostManagementPlanner())->plan([
            ['vm' => 'vm-1', 'technical_responsible_id' => 10],
            ['vm' => 'vm-2', 'technical_responsible_id' => 20],
            ['vm' => 'vm-3', 'technical_responsible_id' => null],
        ], 7, [10]);

        self::assertSame(7, $plan['updates'][0]['management_unit_id']);
        self::assertSame(10, $plan['updates'][0]['technical_responsible_id']);
        self::assertNull($plan['updates'][1]['technical_responsible_id']);
        self::assertNull($plan['updates'][2]['technical_responsible_id']);
        self::assertSame(1, $plan['cleared_responsible_count']);
    }
}
