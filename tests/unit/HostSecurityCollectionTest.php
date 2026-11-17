<?php

use App\Libraries\HostSecurityCollection;
use App\Libraries\HostSecurityStore;
use PHPUnit\Framework\TestCase;

final class HostSecurityCollectionTest extends TestCase
{
    public function testValidationNormalizesAndRejectsInvalidStates(): void
    {
        $parser = new HostSecurityCollection();
        self::assertSame(['hostname' => 'server.example', 'sestatus' => 'enforcing', 'firewall' => 'active'],
            $parser->validate(['hostname' => 'SERVER.EXAMPLE.', 'sestatus' => 'Enforcing', 'firewall' => 'active']));
        $this->expectException(InvalidArgumentException::class);
        $parser->validate(['hostname' => 'server', 'sestatus' => 'Enforcing', 'firewall' => '']);
    }

    public function testReportListsEveryCollectedHostWithoutInventory(): void
    {
        $records = array_map(static fn ($host) => ['hostname' => $host, 'sestatus' => 'enforcing', 'firewall' => 'active', 'collected_at' => '2026-11-17 12:00:00'],
            ['unknown.example', 'same.b', 'same.a', 'host-without-inventory']);
        $result = (new HostSecurityCollection())->report($records);
        self::assertSame(['host-without-inventory', 'same.a', 'same.b', 'unknown.example'], array_column($result['rows'], 'hostname'));
        self::assertSame($records[0], $result['rows'][3]);
    }

    public function testSearchUsesCollectedHostnameAndIgnoresCase(): void
    {
        $records = [
            ['hostname' => 'server.example', 'sestatus' => 'enforcing', 'firewall' => 'active', 'collected_at' => '2026-11-17 12:00:00'],
            ['hostname' => 'other.example', 'sestatus' => 'enforcing', 'firewall' => 'active', 'collected_at' => '2026-11-17 12:00:00'],
        ];
        $builder = new HostSecurityCollection();
        self::assertSame([$records[0]], $builder->report($records, 'SERVER')['rows']);
        self::assertSame([], $builder->report($records, 'missing')['rows']);
        self::assertSame([], $builder->report([])['rows']);
    }

    public function testSummaryCountsEveryHostBeforePaginationAndIncludesOtherFirewallStates(): void
    {
        $records = [];
        for ($i = 0; $i < 60; ++$i) {
            $records[] = ['hostname' => 'host-' . $i,
                'sestatus' => ['enforcing', 'permissive', 'disabled', 'unknown'][$i % 4],
                'firewall' => ['active', 'inactive', 'failed', 'activating', 'unknown'][$i % 5]];
        }
        $result = (new HostSecurityCollection())->report($records);
        self::assertCount(60, $result['rows']);
        foreach ($result['summary']['sestatus'] as $status) {
            self::assertSame(15, $status['count']);
            self::assertSame(25.0, $status['percentage']);
        }
        foreach ($result['summary']['firewall'] as $status) {
            self::assertSame(12, $status['count']);
            self::assertSame(20.0, $status['percentage']);
        }
        self::assertSame(60, array_sum(array_column($result['summary']['firewall'], 'count')));
    }

    public function testSummaryFollowsSearchAndHandlesZeroHosts(): void
    {
        $records = [
            ['hostname' => 'selected.example', 'sestatus' => 'permissive', 'firewall' => 'inactive'],
            ['hostname' => 'other.example', 'sestatus' => 'enforcing', 'firewall' => 'active'],
        ];
        $builder = new HostSecurityCollection();
        $summary = $builder->report($records, 'selected')['summary'];
        self::assertSame(['count' => 1, 'percentage' => 100.0], $summary['sestatus']['permissive']);
        self::assertSame(['count' => 0, 'percentage' => 0.0], $summary['sestatus']['enforcing']);
        self::assertSame(['count' => 1, 'percentage' => 100.0], $summary['firewall']['inactive']);
        foreach ([$builder->report([]), $builder->report($records, 'missing')] as $result) {
            foreach ($result['summary'] as $statuses) {
                foreach ($statuses as $status) {
                    self::assertSame(['count' => 0, 'percentage' => 0.0], $status);
                }
            }
        }
    }

    public function testDuplicatesUseLatestDailyStateBeforeSearchAndSummary(): void
    {
        $base = ['sestatus' => 'disabled', 'firewall' => 'inactive'];
        $records = [
            ['hostname' => 'OLD.example.', 'ip' => '192.0.2.10', 'collected_at' => '2026-11-17 10:00:00'] + $base,
            ['hostname' => 'new.example', 'ip' => '192.0.2.10', 'collected_at' => '2026-11-17 12:00:00', 'sestatus' => 'enforcing', 'firewall' => 'active'],
            ['hostname' => 'old.EXAMPLE', 'ip' => '192.0.2.11', 'collected_at' => '2026-11-17 11:00:00'] + $base,
            ['hostname' => 'separate', 'ip' => '', 'collected_at' => '2026-11-17 09:00:00'] + $base,
            ['hostname' => 'another', 'collected_at' => '2026-11-17 09:00:00'] + $base,
        ];
        $builder = new HostSecurityCollection();
        $result = $builder->report($records);
        self::assertSame(['another', 'new.example', 'separate'], array_column($result['rows'], 'hostname'));
        self::assertSame(1, $result['summary']['sestatus']['enforcing']['count']);
        self::assertSame(2, $result['summary']['sestatus']['disabled']['count']);
        self::assertEqualsWithDelta(100 / 3, $result['summary']['firewall']['active']['percentage'], 0.0001);
        self::assertSame([], $builder->report($records, 'old.example')['rows']);
        self::assertCount(1, $builder->report($records, '192.0.2.10')['rows']);
    }

    public function testDeduplicationSeparatesDaysAndNormalizesIpv6(): void
    {
        $base = ['sestatus' => 'enforcing', 'firewall' => 'active'];
        $records = [
            ['hostname' => 'previous-day', 'ip' => '2001:db8::1', 'collected_at' => '2026-11-16 10:00:00'] + $base,
            ['hostname' => 'first', 'ip' => '2001:0db8:0:0:0:0:0:1', 'collected_at' => '2026-11-17 10:00:00'] + $base,
            ['hostname' => 'last', 'ip' => '2001:db8::1', 'collected_at' => '2026-11-17 10:00:00'] + $base,
        ];
        self::assertSame(['last', 'previous-day'], array_column((new HostSecurityCollection())->report($records)['rows'], 'hostname'));
        $normalized = (new HostSecurityCollection())->validate(['hostname' => 'host', 'ip' => '2001:0db8:0:0:0:0:0:1'] + $base);
        self::assertSame('2001:db8::1', $normalized['ip']);
        $this->expectException(InvalidArgumentException::class);
        (new HostSecurityCollection())->validate(['hostname' => 'host', 'ip' => '192.0.2.1 192.0.2.2'] + $base);
    }

    public function testStoreKeepsDailyHistoryAndReplacesRepeatHost(): void
    {
        $directory = sys_get_temp_dir() . '/rvscope-security-' . bin2hex(random_bytes(8));
        $store = new HostSecurityStore($directory);
        try {
            $input = ['hostname' => 'server', 'sestatus' => 'Enforcing', 'firewall' => 'active'];
            $store->save($input, new DateTimeImmutable('2026-11-17T12:00:00Z'));
            $store->save(array_replace($input, ['firewall' => 'inactive']), new DateTimeImmutable('2026-11-17T13:00:00Z'));
            $store->save($input, new DateTimeImmutable('2026-11-18T12:00:00Z'));
            self::assertSame(['2026-11-18', '2026-11-17'], $store->dates());
            self::assertCount(1, $store->read('2026-11-17'));
            self::assertSame('inactive', $store->read('2026-11-17')[0]['firewall']);
            self::assertSame('2026-11-17 13:00:00', $store->read('2026-11-17')[0]['collected_at']);
            file_put_contents($directory . '/2026-11-17.json', '{broken');
            try {
                $store->save($input, new DateTimeImmutable('2026-11-17T14:00:00Z'));
                self::fail('Histórico inválido não pode ser sobrescrito.');
            } catch (JsonException $exception) {
                self::assertSame('{broken', file_get_contents($directory . '/2026-11-17.json'));
            }
        } finally {
            foreach (glob($directory . '/*') ?: [] as $file) {
                unlink($file);
            }
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }
}
