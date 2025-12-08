<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Dashboard::index');

$routes->group('auth', ['namespace' => 'App\Controllers\Auth'], static function($routes) {
    $routes->get('login', 'LoginController::showLoginForm');
    $routes->post('login', 'LoginController::login');
    $routes->get('logout', 'LoginController::logout');
});

$routes->group('', ['filter' => 'auth'], static function($routes) {
    $routes->get('dashboard', 'Dashboard::index');
    $routes->group('clients', ['namespace' => 'App\Controllers\Clients'], static function($routes) {
        $routes->get('/', 'ClientController::index');
        $routes->get('create', 'ClientController::create');
        $routes->post('store', 'ClientController::store');
        $routes->get('edit/(:num)', 'ClientController::edit/$1');
        $routes->post('update/(:num)', 'ClientController::update/$1');
    });
});
