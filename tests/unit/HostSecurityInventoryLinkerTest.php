<?php

use App\Libraries\HostSecurityCollection;
use PHPUnit\Framework\TestCase;

final class HostSecurityInventoryLinkerTest extends TestCase
{
    public function testIndependentAndLinkedModesUseDifferentPopulations(): void
    {
        $records = $this->records(['allowed', 'off', 'excluded', 'missing']);
        $inventory = [
            ['vm' => 'allowed', 'power_state' => 'poweredOn', 'included_in_reports' => true],
            ['vm' => 'off', 'power_state' => 'poweredOff', 'included_in_reports' => true],
            ['vm' => 'excluded', 'power_state' => 'poweredOn', 'included_in_reports' => false],
        ];
        $builder = new HostSecurityCollection();
        self::assertCount(4, $builder->report($records)['rows']);
        $linked = $builder->report($records, '', $inventory);
        self::assertSame(['allowed'], array_column($linked['rows'], 'hostname'));
        self::assertSame('allowed', $linked['rows'][0]['inventory_vm']);
        self::assertSame(['count' => 1, 'percentage' => 100.0], $linked['summary']['sestatus']['enforcing']);
        self::assertSame([], $builder->report($records, '', [])['rows']);
        self::assertCount(4, $builder->report($records)['rows']);
    }

    public function testDnsIpAndUniqueShortNamesMatchButAmbiguousNamesDoNot(): void
    {
        $records = $this->records(['dns.example', 'short', 'ip-host', 'same']);
        $records[2]['ip'] = '2001:db8::1';
        $eligible = ['power_state' => 'poweredOn', 'included_in_reports' => 't'];
        $inventory = [
            ['vm' => 'vm-dns', 'dns_name' => 'DNS.EXAMPLE.'] + $eligible,
            ['vm' => 'vm-short', 'dns_name' => 'short.example'] + $eligible,
            ['vm' => 'vm-ip', 'primary_ip' => '2001:0db8:0:0:0:0:0:1'] + $eligible,
            ['vm' => 'same.a'] + $eligible,
            ['vm' => 'same.b'] + $eligible,
        ];
        $result = (new HostSecurityCollection())->report($records, '', $inventory);
        self::assertSame(['vm-dns', 'vm-ip', 'vm-short'], array_column($result['rows'], 'inventory_vm'));
    }

    public function testConflictingNameAndIpDoNotLinkToEitherVm(): void
    {
        $records = $this->records(['first']);
        $records[0]['ip'] = '192.0.2.2';
        $eligible = ['power_state' => 'poweredOn', 'included_in_reports' => true];
        $inventory = [
            ['vm' => 'first', 'primary_ip' => '192.0.2.1'] + $eligible,
            ['vm' => 'second', 'primary_ip' => '192.0.2.2'] + $eligible,
        ];
        self::assertSame([], (new HostSecurityCollection())->report($records, '', $inventory)['rows']);
    }

    public function testMultipleNamesForOneVmUseLatestStateInSummaryAndSearch(): void
    {
        $records = $this->records(['old-name', 'new-name']);
        $records[1]['collected_at'] = '2026-11-17 13:00:00';
        $records[1]['sestatus'] = 'permissive';
        $inventory = [['vm' => 'old-name', 'dns_name' => 'new-name', 'power_state' => 'poweredOn', 'included_in_reports' => true]];
        $builder = new HostSecurityCollection();
        $result = $builder->report($records, '', $inventory);
        self::assertSame(['new-name'], array_column($result['rows'], 'hostname'));
        self::assertSame(['count' => 1, 'percentage' => 100.0], $result['summary']['sestatus']['permissive']);
        self::assertSame([], $builder->report($records, 'old-name', $inventory)['rows']);
    }

    private function records(array $hosts): array
    {
        return array_map(static fn ($hostname) => ['hostname' => $hostname, 'sestatus' => 'enforcing', 'firewall' => 'active', 'collected_at' => '2026-11-17 12:00:00'], $hosts);
    }
}
