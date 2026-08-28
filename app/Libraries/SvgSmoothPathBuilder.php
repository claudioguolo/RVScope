<?php

namespace App\Libraries;

final class SvgSmoothPathBuilder
{
    public function build(array $points): string
    {
        $points = array_values(array_filter(
            $points,
            static fn (mixed $point): bool => is_array($point)
                && isset($point[0], $point[1])
                && is_numeric($point[0])
                && is_numeric($point[1])
        ));
        if ($points === []) {
            return '';
        }

        $path = 'M ' . $this->number((float) $points[0][0]) . ' ' . $this->number((float) $points[0][1]);
        if (count($points) === 1) {
            return $path;
        }

        $last = count($points) - 1;
        for ($index = 0; $index < $last; $index++) {
            $previous = $points[max(0, $index - 1)];
            $current = $points[$index];
            $next = $points[$index + 1];
            $following = $points[min($last, $index + 2)];

            $minimumY = min((float) $current[1], (float) $next[1]);
            $maximumY = max((float) $current[1], (float) $next[1]);
            $control1X = (float) $current[0] + ((float) $next[0] - (float) $previous[0]) / 6;
            $control1Y = $this->clamp(
                (float) $current[1] + ((float) $next[1] - (float) $previous[1]) / 6,
                $minimumY,
                $maximumY,
            );
            $control2X = (float) $next[0] - ((float) $following[0] - (float) $current[0]) / 6;
            $control2Y = $this->clamp(
                (float) $next[1] - ((float) $following[1] - (float) $current[1]) / 6,
                $minimumY,
                $maximumY,
            );

            $path .= ' C '
                . $this->number($control1X) . ' ' . $this->number($control1Y) . ' '
                . $this->number($control2X) . ' ' . $this->number($control2Y) . ' '
                . $this->number((float) $next[0]) . ' ' . $this->number((float) $next[1]);
        }

        return $path;
    }

    private function clamp(float $value, float $minimum, float $maximum): float
    {
        return max($minimum, min($maximum, $value));
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }
}
