<?php

namespace App\Controllers;

use App\Libraries\OsHistoryChartBuilder;
use App\Models\RvtoolsOsSummaryModel;
use CodeIgniter\Controller;

class ChartController extends Controller
{
    public function index()
    {
        $rows = (new RvtoolsOsSummaryModel())
            ->select('reference_date, os_name, vm_count')
            ->orderBy('reference_date', 'ASC')
            ->orderBy('os_name', 'ASC')
            ->findAll();

        return view('charts/index', [
            'chart' => (new OsHistoryChartBuilder())->build($rows),
        ]);
    }
}
