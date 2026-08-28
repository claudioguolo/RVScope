<?php

namespace Tests\Unit;

use App\Libraries\SvgSmoothPathBuilder;
use PHPUnit\Framework\TestCase;

final class SvgSmoothPathBuilderTest extends TestCase
{
    public function testBuildsSmoothCubicPathThroughEveryPoint(): void
    {
        $path = (new SvgSmoothPathBuilder())->build([
            [0, 10],
            [20, 5],
            [40, 15],
        ]);

        self::assertStringStartsWith('M 0 10 C ', $path);
        self::assertStringContainsString('20 5 C ', $path);
        self::assertStringEndsWith('40 15', $path);
    }

    public function testHandlesEmptyAndSinglePointSeries(): void
    {
        $builder = new SvgSmoothPathBuilder();

        self::assertSame('', $builder->build([]));
        self::assertSame('M 4 8', $builder->build([[4, 8]]));
    }
}
