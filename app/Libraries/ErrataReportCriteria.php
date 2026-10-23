<?php

namespace App\Libraries;

use DateTimeImmutable;

final class ErrataReportCriteria
{
    public readonly string $term;
    public readonly string $date;
    public readonly string $startDate;
    public readonly string $endDate;
    public readonly int $page;
    private bool $invalidInput = false;

    public function __construct(array $input, string $defaultDate = '')
    {
        foreach (['q' => 'term', 'date' => 'date', 'start_date' => 'startDate', 'end_date' => 'endDate'] as $key => $property) {
            $value = $input[$key] ?? ($key === 'date' ? $defaultDate : '');
            if (! is_string($value)) {
                $this->invalidInput = true;
                $value = '';
            }
            $this->{$property} = trim($value);
        }
        $page = $input['page'] ?? 1;
        $this->page = is_scalar($page) ? max(1, (int) $page) : 1;
    }

    public function error(): ?string
    {
        if ($this->invalidInput || mb_strlen($this->term) > 254) {
            return 'Informe um nome, IP ou hostname com até 254 caracteres e datas válidas.';
        }
        foreach ([$this->date, $this->startDate, $this->endDate] as $value) {
            if ($value !== '' && ! self::validDate($value)) {
                return 'Informe uma data válida no formato dia/mês/ano.';
            }
        }
        if ($this->startDate !== '' && $this->endDate !== '' && $this->startDate > $this->endDate) {
            return 'A data inicial deve ser anterior ou igual à data final.';
        }
        return null;
    }

    public function includesDate(string $date): bool
    {
        return ($this->startDate === '' || $date >= $this->startDate)
            && ($this->endDate === '' || $date <= $this->endDate);
    }

    public static function validDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }
}
