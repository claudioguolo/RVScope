<?php

namespace Tests\Unit;

use App\Libraries\OsHistoryChartCriteria;
use PHPUnit\Framework\TestCase;

final class OsHistoryChartCriteriaTest extends TestCase
{
    public function testDefaultsToOldestNewestAndAllOperatingSystems(): void
    {
        $criteria = OsHistoryChartCriteria::fromArray([], ['Linux', 'Windows'], '2026-01-01', '2026-08-28', false);

        self::assertSame('2026-01-01', $criteria->startDate);
        self::assertSame('2026-08-28', $criteria->endDate);
        self::assertSame(['Linux', 'Windows'], $criteria->operatingSystems);
        self::assertNull($criteria->error());
    }

    public function testKeepsOnlyAvailableSelectionsAndRejectsReversePeriod(): void
    {
        $criteria = OsHistoryChartCriteria::fromArray([
            'start_date' => '2026-08-28',
            'end_date' => '2026-01-01',
            'os' => ['Linux', 'Unknown'],
        ], ['Linux', 'Windows'], '2026-01-01', '2026-08-28', true);

        self::assertSame(['Linux'], $criteria->operatingSystems);
        self::assertSame('A data inicial não pode ser posterior à data final.', $criteria->error());
    }
}
