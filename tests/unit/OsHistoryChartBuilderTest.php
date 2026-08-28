<?php

namespace Tests\Unit;

use App\Libraries\OsHistoryChartBuilder;
use PHPUnit\Framework\TestCase;

final class OsHistoryChartBuilderTest extends TestCase
{
    public function testBuildsChronologicalSeriesAndFillsMissingDatesWithZero(): void
    {
        $chart = (new OsHistoryChartBuilder())->build([
            ['reference_date' => '2026-08-02', 'os_name' => 'Linux', 'vm_count' => 5],
            ['reference_date' => '2026-08-01', 'os_name' => 'Windows', 'vm_count' => 8],
            ['reference_date' => '2026-08-01', 'os_name' => 'Linux', 'vm_count' => 4],
        ]);

        self::assertSame(['2026-08-01', '2026-08-02'], $chart['labels']);
        self::assertSame('Linux', $chart['series'][0]['name']);
        self::assertSame([4, 5], $chart['series'][0]['values']);
        self::assertSame('Windows', $chart['series'][1]['name']);
        self::assertSame([8, 0], $chart['series'][1]['values']);
        self::assertSame(8, $chart['maximum']);
    }
}
