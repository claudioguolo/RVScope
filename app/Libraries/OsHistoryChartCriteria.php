<?php

namespace App\Libraries;

use DateTime;

final class OsHistoryChartCriteria
{
    public function __construct(
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly array $operatingSystems,
    ) {
    }

    public static function fromArray(
        array $input,
        array $availableOperatingSystems,
        string $oldestDate,
        string $newestDate,
        bool $submitted,
    ): self {
        $startDate = trim((string) ($input['start_date'] ?? $oldestDate));
        $endDate = trim((string) ($input['end_date'] ?? $newestDate));

        if (! $submitted) {
            $selected = $availableOperatingSystems;
        } else {
            $requested = array_map(
                static fn (mixed $value): string => trim((string) $value),
                (array) ($input['os'] ?? [])
            );
            $selected = array_values(array_intersect($availableOperatingSystems, array_unique($requested)));
        }

        return new self($startDate, $endDate, $selected);
    }

    public function error(): ?string
    {
        if (! $this->validDate($this->startDate) || ! $this->validDate($this->endDate)) {
            return 'Informe datas inicial e final válidas.';
        }
        if ($this->startDate > $this->endDate) {
            return 'A data inicial não pode ser posterior à data final.';
        }
        if ($this->operatingSystems === []) {
            return 'Selecione pelo menos um sistema operacional.';
        }

        return null;
    }

    private function validDate(string $value): bool
    {
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        $errors = DateTime::getLastErrors();

        return $date !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $date->format('Y-m-d') === $value;
    }
}
