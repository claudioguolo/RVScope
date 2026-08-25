<?php

namespace App\Libraries;

use DateTime;

final class CustomReportCriteria
{
    public function __construct(
        public readonly string $date,
        public readonly array $operatingSystems,
        public readonly array $managementUnitIds,
        public readonly bool $legacy,
        public readonly bool $appliance,
    ) {
    }

    public static function fromArray(array $input, string $defaultDate = ''): self
    {
        return new self(
            trim((string) ($input['date'] ?? $defaultDate)),
            self::strings($input['os'] ?? []),
            self::positiveIntegers($input['management_unit_id'] ?? []),
            self::isChecked($input['legacy'] ?? null),
            self::isChecked($input['appliance'] ?? null),
        );
    }

    public function hasValidDate(): bool
    {
        $date = DateTime::createFromFormat('!Y-m-d', $this->date);
        $errors = DateTime::getLastErrors();

        return $date !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $date->format('Y-m-d') === $this->date;
    }

    private static function isChecked(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'on', 'true'], true);
    }

    private static function strings(mixed $values): array
    {
        $normalized = array_map(
            static fn (mixed $value): string => trim((string) $value),
            is_array($values) ? $values : [$values]
        );

        return array_values(array_unique(array_filter(
            $normalized,
            static fn (string $value): bool => $value !== ''
        )));
    }

    private static function positiveIntegers(mixed $values): array
    {
        $normalized = array_map(
            static fn (mixed $value): int => (int) $value,
            is_array($values) ? $values : [$values]
        );

        return array_values(array_unique(array_filter(
            $normalized,
            static fn (int $value): bool => $value > 0
        )));
    }
}
