<?php

namespace Config;

use CodeIgniter\Config\Services;

$routes = Services::routes();

if (file_exists(SYSTEMPATH . 'Config/Routes.php')) {
    require SYSTEMPATH . 'Config/Routes.php';
}

$routes->setDefaultNamespace('App\Controllers');
$routes->setDefaultController('ReportController');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);
$routes->set404Override();
$routes->setAutoRoute(false);

$routes->get('/', 'ReportController::index');
$routes->get('reports', 'ReportController::index');
$routes->get('reports/vm-por-gerencia', 'ReportController::vmPorGerencia');
$routes->match(['GET', 'POST'], 'reports/vm-por-gerencia/detail', 'ReportController::vmPorGerenciaDetail');
$routes->get('reports/appliances', 'ReportController::appliances');
$routes->match(['GET', 'POST'], 'reports/appliances/detail', 'ReportController::appliancesDetail');
$routes->match(['GET', 'POST'], 'reports/detail', 'ReportController::detail');
$routes->get('import', 'ImportController::index');

if (file_exists(APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php')) {
    require APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php';
}
