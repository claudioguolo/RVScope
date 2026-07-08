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
$routes->get('admin/access', 'AdminController::access');
$routes->get('admin/login', 'AdminController::login');
$routes->post('admin/login', 'AdminController::authenticate', ['filter' => 'csrf']);
$routes->get('admin/users', 'AdminController::users');
$routes->post('admin/users', 'AdminController::createUser', ['filter' => 'csrf']);
$routes->get('admin/profile', 'AdminController::profile');
$routes->post('admin/profile', 'AdminController::updateProfile', ['filter' => 'csrf']);
$routes->post('admin/profile/password', 'AdminController::updatePassword', ['filter' => 'csrf']);
$routes->post('admin/logout', 'AdminController::logout', ['filter' => 'csrf']);
$routes->get('reports/vm', 'ReportController::vmTodos');
$routes->get('reports/vm-migraveis', 'ReportController::vmMigraveis');
$routes->get('reports/vm-migraveis/detail', 'ReportController::vmMigraveisDetail');
$routes->post('reports/vm-migraveis/detail', 'ReportController::vmMigraveisDetail', ['filter' => 'csrf']);
$routes->get('reports/vm-por-gerencia', 'ReportController::vmPorGerencia');
$routes->get('reports/vm-por-gerencia/detail', 'ReportController::vmPorGerenciaDetail');
$routes->post('reports/vm-por-gerencia/detail', 'ReportController::vmPorGerenciaDetail', ['filter' => 'csrf']);
$routes->get('reports/appliances/todos', 'ReportController::appliancesTodos');
$routes->get('reports/appliances', 'ReportController::appliances');
$routes->get('reports/appliances/detail', 'ReportController::appliancesDetail');
$routes->post('reports/appliances/detail', 'ReportController::appliancesDetail', ['filter' => 'csrf']);
$routes->get('reports/detail', 'ReportController::detail');
$routes->post('reports/detail', 'ReportController::detail', ['filter' => 'csrf']);
$routes->get('import', 'ImportController::form');
$routes->post('import', 'ImportController::index');

if (file_exists(APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php')) {
    require APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php';
}
