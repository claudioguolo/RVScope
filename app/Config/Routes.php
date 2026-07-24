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

$routes->get('/', 'ReportController::index', ['filter' => 'authenticatedReports']);
$routes->get('reports', 'ReportController::index', ['filter' => 'authenticatedReports']);
$routes->get('admin/access', 'AdminController::access');
$routes->get('auth/login', 'AuthController::login');
$routes->post('auth/login', 'AuthController::authenticate', ['filter' => 'csrf']);
$routes->post('auth/logout', 'AuthController::logout', ['filter' => 'csrf']);
$routes->get('admin/login', 'AdminController::login');
$routes->post('admin/login', 'AdminController::authenticate', ['filter' => 'csrf']);
$routes->get('admin/users', 'AdminController::users');
$routes->post('admin/users', 'AdminController::createUser', ['filter' => 'csrf']);
$routes->post('admin/settings/authenticated-reports', 'AdminController::updateAuthenticatedReports', ['filter' => 'csrf']);
$routes->post('admin/settings/active-directory', 'AdminController::updateActiveDirectory', ['filter' => 'csrf']);
$routes->post('admin/settings/smtp', 'AdminController::updateSmtp', ['filter' => 'csrf']);
$routes->post('admin/settings/smtp/test', 'AdminController::testSmtp', ['filter' => 'csrf']);
$routes->get('admin/profile', 'AdminController::profile');
$routes->post('admin/profile', 'AdminController::updateProfile', ['filter' => 'csrf']);
$routes->post('admin/profile/password', 'AdminController::updatePassword', ['filter' => 'csrf']);
$routes->post('admin/logout', 'AdminController::logout', ['filter' => 'csrf']);
$routes->group('reports', ['filter' => 'authenticatedReports'], static function ($routes) {
    $routes->get('vm', 'ReportController::vmTodos');
    $routes->get('vm-migraveis', 'ReportController::vmMigraveis');
    $routes->get('vm-migraveis/detail', 'ReportController::vmMigraveisDetail');
    $routes->post('vm-migraveis/detail', 'ReportController::vmMigraveisDetail', ['filter' => 'csrf']);
    $routes->get('vm-por-gerencia', 'ReportController::vmPorGerencia');
    $routes->get('vm-por-gerencia/detail', 'ReportController::vmPorGerenciaDetail');
    $routes->post('vm-por-gerencia/detail', 'ReportController::vmPorGerenciaDetail', ['filter' => 'csrf']);
    $routes->get('appliances/todos', 'ReportController::appliancesTodos');
    $routes->get('appliances', 'ReportController::appliances');
    $routes->get('appliances/detail', 'ReportController::appliancesDetail');
    $routes->post('appliances/detail', 'ReportController::appliancesDetail', ['filter' => 'csrf']);
    $routes->get('detail', 'ReportController::detail');
    $routes->post('detail', 'ReportController::detail', ['filter' => 'csrf']);
});
$routes->get('import', 'ImportController::form');
$routes->post('import', 'ImportController::index');

if (file_exists(APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php')) {
    require APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php';
}
