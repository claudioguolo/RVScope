<?php

namespace Tests\Unit;

use App\Libraries\RvtoolsCsvFileInspector;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RvtoolsCsvFileInspectorTest extends TestCase
{
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function testAcceptsRequiredRvtoolsHeaderAndBom(): void
    {
        $file = $this->csv("\xEF\xBB\xBFVM;Powerstate;DNS Name;OS according to the VMware Tools\n");

        self::assertNull((new RvtoolsCsvFileInspector())->headerError($file));
    }

    public function testRejectsCsvWithoutRequiredColumn(): void
    {
        $file = $this->csv("VM;Powerstate;DNS Name\n");

        self::assertSame(
            'Required column not found: OS according to the VMware Tools',
            (new RvtoolsCsvFileInspector())->headerError($file),
        );
    }

    public function testExtractsStrictReferenceDate(): void
    {
        self::assertSame(
            '2026-08-20',
            (new RvtoolsCsvFileInspector())->referenceDate('RVTools_ExportvInfo2csv_2026-08-20_10.11.12.csv'),
        );
    }

    public function testRejectsImpossibleOrMalformedDate(): void
    {
        $this->expectException(RuntimeException::class);
        (new RvtoolsCsvFileInspector())->referenceDate('RVTools_ExportvInfo2csv_2026-02-31_10.11.12.csv');
    }

    private function csv(string $contents): string
    {
        $file = tempnam(sys_get_temp_dir(), 'rvscope-test-');
        self::assertNotFalse($file);
        file_put_contents($file, $contents);
        $this->temporaryFiles[] = $file;

        return $file;
    }
}
