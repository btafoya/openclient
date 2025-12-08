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
    $routes->group('clients', ['namespace' => 'App\Controllers\Clients'], static function($routes) {
        $routes->get('/', 'ClientController::index');
        $routes->get('create', 'ClientController::create');
        $routes->post('store', 'ClientController::store');
        $routes->get('edit/(:num)', 'ClientController::edit/$1');
        $routes->post('update/(:num)', 'ClientController::update/$1');
    });
});
