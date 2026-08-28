<?php

namespace App\Controllers;

use App\Libraries\OsHistoryChartBuilder;
use App\Libraries\OsHistoryChartCriteria;
use App\Models\RvtoolsOsSummaryModel;
use CodeIgniter\Controller;

class ChartController extends Controller
{
    public function index()
    {
        $allRows = (new RvtoolsOsSummaryModel())
            ->select('reference_date, os_name, vm_count')
            ->orderBy('reference_date', 'ASC')
            ->orderBy('os_name', 'ASC')
            ->findAll();

        $dates = array_values(array_unique(array_map(
            static fn (array $row): string => (string) ($row['reference_date'] ?? ''),
            $allRows
        )));
        $operatingSystems = array_values(array_unique(array_map(
            static fn (array $row): string => (string) ($row['os_name'] ?? ''),
            $allRows
        )));
        $dates = array_values(array_filter($dates));
        $operatingSystems = array_values(array_filter($operatingSystems));
        sort($dates, SORT_STRING);
        usort($operatingSystems, 'strcasecmp');

        $submitted = $this->request->getGet('filter') === '1';
        $criteria = OsHistoryChartCriteria::fromArray(
            $this->request->getGet(),
            $operatingSystems,
            (string) ($dates[0] ?? ''),
            (string) ($dates[count($dates) - 1] ?? ''),
            $submitted,
        );
        $error = $allRows === [] ? null : $criteria->error();
        $rows = [];
        if ($error === null) {
            $selectedOs = array_fill_keys($criteria->operatingSystems, true);
            $rows = array_values(array_filter(
                $allRows,
                static function (array $row) use ($criteria, $selectedOs): bool {
                    $date = (string) ($row['reference_date'] ?? '');
                    $os = (string) ($row['os_name'] ?? '');

                    return $date >= $criteria->startDate
                        && $date <= $criteria->endDate
                        && isset($selectedOs[$os]);
                }
            ));
        }

        return view('charts/index', [
            'chart' => (new OsHistoryChartBuilder())->build($rows),
            'criteria' => $criteria,
            'availableOperatingSystems' => $operatingSystems,
            'oldestDate' => (string) ($dates[0] ?? ''),
            'newestDate' => (string) ($dates[count($dates) - 1] ?? ''),
            'error' => $error,
        ]);
    }
}
