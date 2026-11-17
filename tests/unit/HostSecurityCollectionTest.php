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

    public function testMatchingFiltersPowerPolicyAndAmbiguousShortNames(): void
    {
        $inventory = [
            ['vm' => 'one', 'dns_name' => 'one.example', 'power_state' => 'poweredOn', 'included_in_reports' => true],
            ['vm' => 'off', 'power_state' => 'poweredOff', 'included_in_reports' => true],
            ['vm' => 'excluded', 'power_state' => 'poweredOn', 'included_in_reports' => false],
            ['vm' => 'same.a', 'power_state' => 'poweredOn', 'included_in_reports' => true],
            ['vm' => 'same.b', 'power_state' => 'poweredOn', 'included_in_reports' => true],
        ];
        $records = array_map(static fn ($host) => ['hostname' => $host, 'sestatus' => 'enforcing', 'firewall' => 'active', 'collected_at' => '2026-11-17 12:00:00'],
            ['one.example', 'off', 'excluded', 'same', 'missing']);
        $result = (new HostSecurityCollection())->report($records, $inventory);
        self::assertSame(['one'], array_column($result['rows'], 'vm'));
        self::assertSame(2, $result['excluded']);
        self::assertSame(1, $result['ambiguous']);
        self::assertSame(1, $result['unmatched']);
        self::assertSame([], (new HostSecurityCollection())->report($records, $inventory, 'missing')['rows']);
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
