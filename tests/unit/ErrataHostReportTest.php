<?php

namespace Tests\Unit;

use App\Libraries\ErrataDataParser;
use App\Libraries\ErrataReportBuilder;
use PHPUnit\Framework\TestCase;

final class ErrataHostReportTest extends TestCase
{
    private function vm(string $name, string $ip, bool $included = true, string $state = 'poweredOn'): array
    {
        return ['vm' => $name, 'dns_name' => strtolower($name) . '.example.org', 'primary_ip' => $ip,
            'included_in_reports' => $included, 'power_state' => $state];
    }

    public function testSearchIsPartialCaseInsensitiveAndRespectsVmPolicies(): void
    {
        $inventory = [$this->vm('dsa-vra-0001', '192.0.2.1'), $this->vm('vra-DSA-0002', '192.0.2.2'),
            $this->vm('dsateste-0001', '192.0.2.3'), $this->vm('dsa-ignored', '192.0.2.4', false),
            $this->vm('dsa-off', '192.0.2.5', true, 'poweredOff')];
        $csv = "collected_at;host;ip;security;bugfix;enhancement;unspecified\n";
        foreach ($inventory as $vm) {
            $csv .= '2026-10-23 10:00:00;' . $vm['vm'] . ';' . $vm['primary_ip'] . ";3;1;0;0\n";
        }
        $records = (new ErrataDataParser())->hosts($csv)['records'];
        $result = (new ErrataReportBuilder())->hosts($records, $inventory, '2026-10-23', 'DSA');
        self::assertSame(3, $result['total']);
        self::assertSame(12, $result['errata_total']);
        self::assertSame(2, $result['excluded']);
        self::assertSame(1, (new ErrataReportBuilder())->hosts($records, $inventory, '2026-10-23', '192.0.2.2')['total']);
        self::assertSame(0, (new ErrataReportBuilder())->hosts($records, $inventory, '2026-10-23', '%')['total']);
        self::assertSame(0, (new ErrataReportBuilder())->hosts($records, $inventory, '2026-10-22')['total']);
    }

    public function testAvoidsAmbiguousHostnamesOrReusedIpsIncludingExcludedVms(): void
    {
        $inventory = [$this->vm('dsa-1', '192.0.2.1'), $this->vm('dsa-2', '192.0.2.1', false)];
        $records = (new ErrataDataParser())->hosts("collected_at;host;ip;total\n2026-10-23;dsa-1;192.0.2.1;5\n2026-10-23;unknown;;10\n")['records'];
        $result = (new ErrataReportBuilder())->hosts($records, $inventory, '2026-10-23');
        self::assertSame(0, $result['total']);
        self::assertSame(1, $result['ambiguous']);
        self::assertSame(1, $result['unmatched']);
    }

    public function testFullHostnameIsRequiredForAssociationAndLatestSnapshotIsUsed(): void
    {
        $inventory = [$this->vm('dsa-1', '192.0.2.1')];
        $records = (new ErrataDataParser())->hosts("collected_at;hostname;total\n2026-10-23 09:00:00;DSA-1.EXAMPLE.ORG.;10\n2026-10-23 10:00:00;DSA-1.EXAMPLE.ORG.;7\n2026-10-23 10:00:00;dsa;99\n")['records'];
        $result = (new ErrataReportBuilder())->hosts($records, $inventory, '2026-10-23');
        self::assertSame(1, $result['total']);
        self::assertSame(7, $result['errata_total']);
        self::assertSame(1, $result['unmatched']);
    }

    public function testIndividualErrataAreDeduplicatedPerHost(): void
    {
        $records = (new ErrataDataParser())->hosts("collected_at;hostname;erratum_id\n2026-10-23;dsa-1.example.org;RHSA-2026:123\n2026-10-23;dsa-1.example.org;RHSA-2026:123\n2026-10-23;dsa-1.example.org;RHBA-2026:234\n")['records'];
        $result = (new ErrataReportBuilder())->hosts($records, [$this->vm('dsa-1', '192.0.2.1')], '2026-10-23');
        self::assertSame(2, $result['errata_total']);
        self::assertSame(1, $result['counts']['security']);
        self::assertSame(1, $result['counts']['bugfix']);
    }

    public function testDifferentSatelliteIdentitiesForTheSameVmAreAmbiguous(): void
    {
        $records = (new ErrataDataParser())->hosts("collected_at;host;hostname;total\n2026-10-23;dsa-1;;5\n2026-10-23;;dsa-1.example.org;5\n")['records'];
        $result = (new ErrataReportBuilder())->hosts($records, [$this->vm('dsa-1', '192.0.2.1')], '2026-10-23');
        self::assertSame(0, $result['errata_total']);
        self::assertSame(2, $result['ambiguous']);
    }

    public function testPaginationKeepsTotalsForAllMatches(): void
    {
        $inventory = [];
        $csv = "collected_at;host;total\n";
        for ($index = 1; $index <= 51; ++$index) {
            $inventory[] = $this->vm('host-' . $index, '192.0.2.' . $index);
            $csv .= '2026-10-23;host-' . $index . ";2\n";
        }
        $result = (new ErrataReportBuilder())->hosts((new ErrataDataParser())->hosts($csv)['records'], $inventory, '2026-10-23', '', 2);
        self::assertSame(51, $result['total']);
        self::assertSame(102, $result['errata_total']);
        self::assertCount(1, $result['rows']);
        self::assertSame(2, $result['pages']);
    }
}
