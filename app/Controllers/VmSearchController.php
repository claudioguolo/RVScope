<?php

namespace App\Controllers;

use App\Models\RvtoolsVmInventoryModel;
use CodeIgniter\Controller;

class VmSearchController extends Controller
{
    public function index()
    {
        helper('url');
        $inventory = new RvtoolsVmInventoryModel();
        $dates = array_column($inventory->select('reference_date')->distinct()
            ->orderBy('reference_date', 'DESC')->findAll(), 'reference_date');
        $query = $this->request->getGet('q');
        $dateInput = $this->request->getGet('date');
        $pageInput = $this->request->getGet('page');
        $term = is_string($query) ? trim($query) : '';
        $date = is_string($dateInput) ? $dateInput : (string) ($dates[0] ?? '');
        $page = is_scalar($pageInput) ? max(1, (int) $pageInput) : 1;
        $error = null;
        $result = ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
        $submitted = $query !== null;
        if ($submitted && $dates !== []) {
            if (! is_string($query) || $term === '' || mb_strlen($term) > 254) {
                $error = 'Informe um nome, IP ou hostname com até 254 caracteres.';
            } elseif (! in_array($date, $dates, true)) {
                $error = 'Selecione uma data de inventário válida.';
            } else {
                $result = $inventory->searchHosts($date, $term, $page);
            }
        }
        $details = new \App\Libraries\HostDetailData();
        $result['rows'] = $details->enrich($result['rows']);
        return view('reports/search', compact('dates', 'date', 'term', 'submitted', 'error', 'result')
            + $details->catalogs() + ['alert' => session()->getFlashdata('hostInfoAlert')]);
    }
}
