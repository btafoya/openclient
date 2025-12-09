<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Dashboard::index');

$routes->group('auth', ['namespace' => 'App\Controllers'], static function($routes) {
    // Login routes
    $routes->get('login', 'AuthController::showLogin');
    $routes->post('login', 'AuthController::login');
    $routes->get('logout', 'AuthController::logout');

    // Password reset routes
    $routes->get('forgot-password', 'AuthController::showForgotPassword');
    $routes->post('forgot-password', 'AuthController::forgotPassword');
    $routes->get('reset-password/(:any)', 'AuthController::showResetPassword/$1');
    $routes->post('reset-password', 'AuthController::resetPassword');
});

$routes->group('', ['filter' => 'auth'], static function($routes) {
    $routes->get('dashboard', 'Dashboard::index');

    // Client routes
    $routes->group('clients', ['namespace' => 'App\Controllers\Clients'], static function($routes) {
        $routes->get('/', 'ClientController::index');
        $routes->get('create', 'ClientController::create');
        $routes->post('/', 'ClientController::store');
        $routes->get('(:segment)', 'ClientController::show/$1');
        $routes->get('(:segment)/edit', 'ClientController::edit/$1');
        $routes->put('(:segment)', 'ClientController::update/$1');
        $routes->delete('(:segment)', 'ClientController::delete/$1');
        $routes->post('(:segment)/restore', 'ClientController::restore/$1');
        $routes->post('(:segment)/toggle-active', 'ClientController::toggleActive/$1');
    });

    // Contact routes
    $routes->group('contacts', ['namespace' => 'App\Controllers\Contacts'], static function($routes) {
        $routes->get('/', 'ContactController::index');
        $routes->get('create', 'ContactController::create');
        $routes->post('/', 'ContactController::store');
        $routes->get('(:segment)', 'ContactController::show/$1');
        $routes->get('(:segment)/edit', 'ContactController::edit/$1');
        $routes->put('(:segment)', 'ContactController::update/$1');
        $routes->delete('(:segment)', 'ContactController::delete/$1');
        $routes->post('(:segment)/restore', 'ContactController::restore/$1');
        $routes->post('(:segment)/toggle-active', 'ContactController::toggleActive/$1');
    });

    // Note routes
    $routes->group('notes', ['namespace' => 'App\Controllers\Notes'], static function($routes) {
        $routes->get('/', 'NoteController::index');
        $routes->get('create', 'NoteController::create');
        $routes->post('/', 'NoteController::store');
        $routes->get('(:segment)', 'NoteController::show/$1');
        $routes->get('(:segment)/edit', 'NoteController::edit/$1');
        $routes->put('(:segment)', 'NoteController::update/$1');
        $routes->delete('(:segment)', 'NoteController::delete/$1');
        $routes->post('(:segment)/restore', 'NoteController::restore/$1');
        $routes->post('(:segment)/toggle-pin', 'NoteController::togglePin/$1');
    });

    // Timeline routes
    $routes->group('timeline', ['namespace' => 'App\Controllers\Timeline'], static function($routes) {
        $routes->get('/', 'TimelineController::index');
        $routes->get('create', 'TimelineController::create');
        $routes->post('/', 'TimelineController::store');
        $routes->get('(:segment)', 'TimelineController::show/$1');
        $routes->delete('(:segment)', 'TimelineController::delete/$1');
    });

    // CSV Import routes
    $routes->group('csv/import', ['namespace' => 'App\Controllers\CsvImport'], static function($routes) {
        $routes->get('/', 'CsvImportController::index');
        $routes->post('upload', 'CsvImportController::upload');
        $routes->get('(:segment)/mapping', 'CsvImportController::mapping/$1');
        $routes->post('(:segment)/mapping', 'CsvImportController::saveMapping/$1');
        $routes->get('(:segment)', 'CsvImportController::show/$1');
        $routes->post('(:segment)/cancel', 'CsvImportController::cancel/$1');
        $routes->delete('(:segment)', 'CsvImportController::delete/$1');
        $routes->get('template/download', 'CsvImportController::downloadTemplate');
    });

    // CSV Export routes
    $routes->group('csv/export', ['namespace' => 'App\Controllers\CsvImport'], static function($routes) {
        $routes->get('/', 'CsvExportController::index');
        $routes->post('/', 'CsvExportController::export');
        $routes->get('fields', 'CsvExportController::getFields');
    });

    // CSV History route
    $routes->get('csv/history', 'CsvImport\CsvImportController::history');

    // API routes
    $routes->group('api', static function($routes) {
        $routes->group('clients', ['namespace' => 'App\Controllers\Clients'], static function($routes) {
            $routes->get('/', 'ClientController::apiIndex');
            $routes->get('(:segment)', 'ClientController::apiShow/$1');
        });
        $routes->group('contacts', ['namespace' => 'App\Controllers\Contacts'], static function($routes) {
            $routes->get('/', 'ContactController::apiIndex');
            $routes->get('(:segment)', 'ContactController::apiShow/$1');
        });
        $routes->group('notes', ['namespace' => 'App\Controllers\Notes'], static function($routes) {
            $routes->get('/', 'NoteController::apiIndex');
            $routes->get('(:segment)', 'NoteController::apiShow/$1');
        });
        $routes->group('timeline', ['namespace' => 'App\Controllers\Timeline'], static function($routes) {
            $routes->get('/', 'TimelineController::apiIndex');
            $routes->get('(:segment)', 'TimelineController::apiShow/$1');
            $routes->get('statistics', 'TimelineController::apiStatistics');
        });
        $routes->group('csv', ['namespace' => 'App\Controllers\CsvImport'], static function($routes) {
            $routes->get('import/statistics', 'CsvImportController::apiStatistics');
        });
    });
});
