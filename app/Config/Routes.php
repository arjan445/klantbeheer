<?php

use App\Controllers\Account;
use App\Controllers\Billing;
use App\Controllers\CostPools;
use App\Controllers\Customers;
use App\Controllers\Dashboard;
use App\Controllers\Devices;
use App\Controllers\Domains;
use App\Controllers\Search;
use App\Controllers\Services;
use App\Controllers\Settings;
use App\Controllers\Sites;
use App\Controllers\Subscriptions;
use App\Controllers\Users;
use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// Inloggen (Shield). Registreren staat uit: gebruikers maak je aan via Beheer.
service('auth')->routes($routes, ['except' => ['register']]);

$routes->get('/', [Dashboard::class, 'index']);
$routes->get('norights', [Dashboard::class, 'norights']);
$routes->get('zoeken', [Search::class, 'index'], ['filter' => 'can:customers.view']);
$routes->match(['GET', 'POST'], 'account', [Account::class, 'index']);

// Klanten
$routes->group('klanten', ['filter' => 'can:customers.view'], static function ($routes) {
    $routes->get('/', [Customers::class, 'index']);
    $routes->match(['GET', 'POST'], 'nieuw', [Customers::class, 'create'], ['filter' => 'can:customers.manage']);
    $routes->get('(:num)', [Customers::class, 'show/$1']);
    $routes->match(['GET', 'POST'], '(:num)/bewerken', [Customers::class, 'edit/$1'], ['filter' => 'can:customers.manage']);
    $routes->post('(:num)/verwijderen', [Customers::class, 'delete/$1'], ['filter' => 'can:customers.manage']);

    // Locaties
    $routes->match(['GET', 'POST'], '(:num)/locaties/nieuw', [Sites::class, 'create/$1'], ['filter' => 'can:customers.manage']);
    $routes->match(['GET', 'POST'], 'locaties/(:num)/bewerken', [Sites::class, 'edit/$1'], ['filter' => 'can:customers.manage']);
    $routes->post('locaties/(:num)/verwijderen', [Sites::class, 'delete/$1'], ['filter' => 'can:customers.manage']);
});

// Diensten (catalogus)
$routes->group('diensten', ['filter' => 'can:billing.view'], static function ($routes) {
    $routes->get('/', [Services::class, 'index']);
    $routes->match(['GET', 'POST'], 'nieuw', [Services::class, 'create'], ['filter' => 'can:billing.manage']);
    $routes->match(['GET', 'POST'], '(:num)/bewerken', [Services::class, 'edit/$1'], ['filter' => 'can:billing.manage']);
    $routes->post('(:num)/verwijderen', [Services::class, 'delete/$1'], ['filter' => 'can:billing.manage']);
});

// Kostprijzen (kostenposten per leverancier, verdeeld over diensten)
$routes->group('kostprijzen', ['filter' => 'can:billing.view'], static function ($routes) {
    $routes->get('/', [CostPools::class, 'index']);
    $routes->match(['GET', 'POST'], 'nieuw', [CostPools::class, 'create'], ['filter' => 'can:billing.manage']);
    $routes->match(['GET', 'POST'], '(:num)/bewerken', [CostPools::class, 'edit/$1'], ['filter' => 'can:billing.manage']);
    $routes->post('(:num)/verwijderen', [CostPools::class, 'delete/$1'], ['filter' => 'can:billing.manage']);
});

// Abonnementen
$routes->group('abonnementen', ['filter' => 'can:billing.view'], static function ($routes) {
    $routes->get('/', [Subscriptions::class, 'index']);
    $routes->match(['GET', 'POST'], 'nieuw', [Subscriptions::class, 'create'], ['filter' => 'can:billing.manage']);
    $routes->match(['GET', 'POST'], '(:num)/bewerken', [Subscriptions::class, 'edit/$1'], ['filter' => 'can:billing.manage']);
    $routes->post('(:num)/verwijderen', [Subscriptions::class, 'delete/$1'], ['filter' => 'can:billing.manage']);
});

// Domeinen
$routes->group('domeinen', ['filter' => 'can:billing.view'], static function ($routes) {
    $routes->get('/', [Domains::class, 'index']);
    $routes->match(['GET', 'POST'], 'nieuw', [Domains::class, 'create'], ['filter' => 'can:billing.manage']);
    $routes->match(['GET', 'POST'], 'snel', [Domains::class, 'quick'], ['filter' => 'can:billing.manage']);
    $routes->match(['GET', 'POST'], '(:num)/bewerken', [Domains::class, 'edit/$1'], ['filter' => 'can:billing.manage']);
    $routes->post('(:num)/verwijderen', [Domains::class, 'delete/$1'], ['filter' => 'can:billing.manage']);
});

// Facturatie
$routes->group('facturatie', ['filter' => 'can:billing.view'], static function ($routes) {
    $routes->get('/', [Billing::class, 'index']);
    $routes->post('factureren', [Billing::class, 'invoice'], ['filter' => 'can:billing.manage']);
    $routes->get('historie', [Billing::class, 'history']);
});

// Kluis: apparaten en wachtwoorden
$routes->group('apparaten', ['filter' => 'can:vault.view'], static function ($routes) {
    $routes->get('/', [Devices::class, 'index']);
    $routes->match(['GET', 'POST'], 'nieuw', [Devices::class, 'create'], ['filter' => 'can:vault.manage']);
    $routes->get('(:num)', [Devices::class, 'show/$1']);
    $routes->match(['GET', 'POST'], '(:num)/bewerken', [Devices::class, 'edit/$1'], ['filter' => 'can:vault.manage']);
    $routes->post('(:num)/verwijderen', [Devices::class, 'delete/$1'], ['filter' => 'can:vault.manage']);
    $routes->post('(:num)/geheim', [Devices::class, 'reveal/$1'], ['filter' => 'throttle:60,60']);
});

// Beheer
$routes->group('beheer', static function ($routes) {
    $routes->get('gebruikers', [Users::class, 'index'], ['filter' => 'can:users.manage']);
    $routes->match(['GET', 'POST'], 'gebruikers/nieuw', [Users::class, 'create'], ['filter' => 'can:users.manage']);
    $routes->match(['GET', 'POST'], 'gebruikers/(:num)', [Users::class, 'edit/$1'], ['filter' => 'can:users.manage']);
    $routes->get('moneybird', [Settings::class, 'moneybird'], ['filter' => 'can:settings.manage']);
    $routes->post('moneybird/contacten', [Settings::class, 'importContacts'], ['filter' => 'can:settings.manage']);
    $routes->get('kluislog', [Settings::class, 'vaultLog'], ['filter' => 'can:settings.manage']);
});
