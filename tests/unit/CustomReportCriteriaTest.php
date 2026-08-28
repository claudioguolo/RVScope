<?php

namespace Tests\Unit;

use App\Libraries\CustomReportCriteria;
use PHPUnit\Framework\TestCase;

final class CustomReportCriteriaTest extends TestCase
{
    public function testNormalizesSelectedFilters(): void
    {
        $criteria = CustomReportCriteria::fromArray([
            'date' => '2026-08-24',
            'os' => [' Red Hat Enterprise Linux 9 ', 'Windows Server 2022', ''],
            'management_unit_id' => ['5', '2', '5', '-1'],
            'group_by' => 'operating_system',
            'legacy' => '1',
            'appliance' => 'on',
            'migrable' => 'true',
        ]);

        self::assertTrue($criteria->hasValidDate());
        self::assertSame(
            ['Red Hat Enterprise Linux 9', 'Windows Server 2022'],
            $criteria->operatingSystems
        );
        self::assertSame([5, 2], $criteria->managementUnitIds);
        self::assertSame(CustomReportCriteria::GROUP_OPERATING_SYSTEM, $criteria->groupBy);
        self::assertTrue($criteria->legacy);
        self::assertTrue($criteria->appliance);
        self::assertTrue($criteria->migrable);
    }

    public function testRejectsImpossibleDateAndUsesSafeDefaults(): void
    {
        $criteria = CustomReportCriteria::fromArray([
            'date' => '2026-02-30',
            'management_unit_id' => '-3',
        ]);

        self::assertFalse($criteria->hasValidDate());
        self::assertSame([], $criteria->managementUnitIds);
        self::assertSame([], $criteria->operatingSystems);
        self::assertSame(CustomReportCriteria::GROUP_MANAGEMENT_UNIT, $criteria->groupBy);
        self::assertFalse($criteria->legacy);
        self::assertFalse($criteria->appliance);
        self::assertFalse($criteria->migrable);
    }
}
