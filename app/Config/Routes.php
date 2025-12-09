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
    });
});
