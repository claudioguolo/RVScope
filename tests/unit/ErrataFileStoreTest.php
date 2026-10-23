<?php

namespace Tests\Unit;

use App\Libraries\ErrataFileStore;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ErrataFileStoreTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/rvscope-erratas-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $path) {
            unlink($path);
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function testPreservesHistoryAndRetryDoesNotDuplicateCollection(): void
    {
        $store = new ErrataFileStore($this->directory);
        self::assertFalse($store->read('daily')['exists']);
        $store->merge('daily', "22-10-202610:00 - 267 bugfix;6 enhancement;486 security;3 unspecified;\n");
        $collection = "23-10-202610:00 - 260 bugfix;5 enhancement;469 security;3 unspecified;\n";
        $store->merge('daily', $collection);
        $result = (new ErrataFileStore($this->directory))->merge('daily', $collection);
        self::assertSame(2, $result['stored']);
        $source = $store->read('daily');
        self::assertSame([], $source['invalid_lines']);
        self::assertSame([762, 737], array_column($source['records'], 'total'));
    }

    public function testInvalidImportCannotReplaceExistingHistory(): void
    {
        $store = new ErrataFileStore($this->directory);
        $store->merge('daily', '23-10-202610:00 - 4 security;');
        $before = file_get_contents($this->directory . '/erratas.txt');
        try {
            $store->merge('daily', "24-10-202610:00 - 12 security;\ncorrupt data");
            self::fail('Uma importação inválida deve ser recusada integralmente.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('linha', $exception->getMessage());
        }
        self::assertSame($before, file_get_contents($this->directory . '/erratas.txt'));
    }

    public function testUpdatesSameTimestampAndKeepsDifferentHosts(): void
    {
        $store = new ErrataFileStore($this->directory);
        $header = "collected_at;host;hostname;ip;security;bugfix;enhancement;unspecified\n";
        $store->merge('hosts', $header . "2026-10-23 10:00:00;dsa-1;;192.0.2.1;12;4;1;0\n2026-10-23 10:00:00;dsa-2;;192.0.2.2;1;0;0;0\n");
        $store->merge('hosts', $header . "2026-10-23 10:00:00;dsa-1;;192.0.2.1;10;4;1;0\n");
        $source = $store->read('hosts');
        self::assertSame([], $source['invalid_lines']);
        self::assertCount(2, $source['records']);
        self::assertSame([15, 1], array_column($source['records'], 'total'));
    }

    public function testIndividualIdsRemainSeparateAndDoNotDuplicateOnRetry(): void
    {
        $store = new ErrataFileStore($this->directory);
        $csv = "collected_at,hostname,erratum_id\n2026-10-23 10:00:00,dsa.example.org,RHSA-2026:123\n2026-10-23 10:00:00,dsa.example.org,RHBA-2026:234\n";
        $store->merge('hosts', $csv);
        self::assertSame(2, $store->merge('hosts', $csv)['stored']);
        self::assertSame([], $store->read('hosts')['invalid_lines']);
    }

    public function testKindCannotSelectAnArbitraryPath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ErrataFileStore($this->directory))->read('../.env');
    }
}
