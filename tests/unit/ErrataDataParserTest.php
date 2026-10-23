<?php

namespace Tests\Unit;

use App\Libraries\ErrataDataParser;
use App\Libraries\ErrataReportBuilder;
use App\Libraries\ErrataReportCriteria;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ErrataDataParserTest extends TestCase
{
    public function testReadsTheActualSatelliteCronFormatAndSumsQuantities(): void
    {
        $source = (new ErrataDataParser())->daily("19-09-202610:00 - 242 bugfix ; 4 enhancement ; 377 security ; 2 unspecified ;\n23-10-202610:00 - 260 bugfix ; 5 enhancement ; 469 security ; 3 unspecified ;\n");
        self::assertSame([], $source['invalid_lines']);
        self::assertSame('2026-10-23 10:00:00', $source['records'][1]['collected_at']);
        self::assertSame(737, $source['records'][1]['total']);
        self::assertSame(['security' => 469, 'bugfix' => 260, 'enhancement' => 5, 'unspecified' => 3], $source['records'][1]['counts']);
        self::assertSame('260 bugfix', $source['records'][1]['items'][0]['label']);
        $days = (new ErrataReportBuilder())->daily($source['records'], new ErrataReportCriteria([]));
        self::assertSame(['2026-10-23', '2026-09-19'], array_column($days, 'date'));
        self::assertSame(625, $days[1]['latest']['total']);
    }

    public function testChoosesTheLatestCollectionWithoutSummingDailySnapshots(): void
    {
        $records = (new ErrataDataParser())->daily("23-10-202610:00 - 260 bugfix;5 enhancement;469 security;3 unspecified;\n23-10-202609:00 - 267 bugfix;6 enhancement;486 security;3 unspecified;\n")['records'];
        $days = (new ErrataReportBuilder())->daily($records, new ErrataReportCriteria([]));
        self::assertCount(1, $days);
        self::assertCount(2, $days[0]['collections']);
        self::assertSame(737, $days[0]['latest']['total']);
    }

    public function testRejectsInvalidDatesAndQuantitiesAndKeepsValidLines(): void
    {
        $source = (new ErrataDataParser())->daily("linha sem separador\n31-09-202610:00 - 1 security;\n23-10-202625:00 - 1 security;\n23-10-202610:00 - -2 security;\n23-10-202610:00 - 1.5 security;\n23-10-202610:00 - 99999999999 security;\n23-10-202610:00 - ;\n23-10-202610:00 - 0 bugfix;0 enhancement;0 security;0 unspecified;\n");
        self::assertSame([1, 2, 3, 4, 5, 6, 7], $source['invalid_lines']);
        self::assertCount(1, $source['records']);
        self::assertSame(0, $source['records'][0]['total']);
    }

    public function testAcceptsBomIsoDatesAndCountLabelsWithoutTreatingIdsAsCounts(): void
    {
        $source = (new ErrataDataParser())->daily("\xEF\xBB\xBF2026-10-23 10:00:00 - security: 10;bugfix: 2;enhancement: 1;unspecified: 0;total: 13;\r\n2026-10-22 - RHSA-2026:12345; RHBA-2026:2222;RHSA-2026:12345;\r\n");
        self::assertSame([], $source['invalid_lines']);
        self::assertSame(13, $source['records'][0]['total']);
        self::assertSame(2, $source['records'][1]['total']);
        self::assertSame(1, $source['records'][1]['counts']['security']);
    }

    public function testReadsCsvCountsAndReportsInvalidRows(): void
    {
        $source = (new ErrataDataParser())->hosts("collected_at;host;hostname;ip;security;bugfix;enhancement;unspecified;total\n2026-10-23 10:00:00;DSA-0001;dsa.example.org;192.0.2.1;12;4;1;0;17\n2026-10-23 10:00:00;bad;;not-an-ip;1;0;0;0;1\n2026-10-23 10:00:00;bad;;;1;0;0;0;10\n");
        self::assertSame([3, 4], $source['invalid_lines']);
        self::assertSame(17, $source['records'][0]['total']);
        self::assertSame('DSA-0001', $source['records'][0]['host']);
    }

    public function testReadsIndividualErrataCsvAndRejectsMixedCounts(): void
    {
        $source = (new ErrataDataParser())->hosts("collected_at,hostname,erratum_id,security\n2026-10-23 10:00:00,dsa.example.org,RHSA-2026:123,\n2026-10-23 10:00:00,dsa.example.org,RHSA-2026:123,1\n");
        self::assertSame([3], $source['invalid_lines']);
        self::assertSame(1, $source['records'][0]['total']);
    }

    public function testRequiresAHostTimestampAndCountsInCsvHeader(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ErrataDataParser())->hosts("date;host;quantity\n2026-10-23;dsa;5\n");
    }

    #[DataProvider('invalidCriteria')]
    public function testValidatesFiltersWithoutPhpWarnings(array $input): void
    {
        self::assertNotNull((new ErrataReportCriteria($input))->error());
    }

    public static function invalidCriteria(): array
    {
        return [[['q' => ['dsa']]], [['q' => str_repeat('a', 255)]], [['date' => '2026-02-30']],
            [['start_date' => '2026-11-01', 'end_date' => '2026-10-01']], [['end_date' => ['2026-10-23']]]];
    }

    public function testDateFilterIsInclusive(): void
    {
        $criteria = new ErrataReportCriteria(['start_date' => '2026-10-22', 'end_date' => '2026-10-23']);
        self::assertNull($criteria->error());
        self::assertFalse($criteria->includesDate('2026-10-21'));
        self::assertTrue($criteria->includesDate('2026-10-22'));
        self::assertTrue($criteria->includesDate('2026-10-23'));
        self::assertFalse($criteria->includesDate('2026-10-24'));
    }
}
