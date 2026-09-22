<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Http\Controller\ActivitiesController;
use App\Http\Controller\DashboardController;
use App\Http\Controller\DealController;
use App\Http\Controller\DealsController;
use App\Http\Controller\TasksController;
use App\Service\Crm\CrmServiceInterface;
use App\Service\Crm\MockCrmService;
use App\Service\DealMetricsCalculator;
use App\Service\DealQueryService;
use App\Service\DealViewFactory;
use App\Support\Request;
use App\Support\Router;
use App\Support\View;

date_default_timezone_set('Europe/Warsaw');

$now = new DateTimeImmutable('now');

// Single composition point: swap MockCrmService for a real CRM adapter
// (e.g. a Bitrix24 client implementing CrmServiceInterface) here — nothing
// else in the app needs to change.
$crm = new MockCrmService($now);
assert($crm instanceof CrmServiceInterface);

$calculator = new DealMetricsCalculator();
$dealViewFactory = new DealViewFactory($crm, $calculator);
$queryService = new DealQueryService();
$view = new View(__DIR__ . '/../templates');

$router = new Router();
$router->get('/', function (Request $request) use ($crm, $dealViewFactory, $view, $now): void {
    (new DashboardController($crm, $dealViewFactory, $view, $now))->index($request);
});
$router->get('/deals', function (Request $request) use ($crm, $dealViewFactory, $queryService, $view, $now): void {
    (new DealsController($crm, $dealViewFactory, $queryService, $view, $now))->index($request);
});
$router->get('/deals/{id}', function (Request $request, array $params) use ($crm, $dealViewFactory, $view, $now): void {
    (new DealController($crm, $dealViewFactory, $view, $now))->show($request, $params);
});
$router->get('/activities', function (Request $request) use ($crm, $view, $now): void {
    (new ActivitiesController($crm, $view, $now))->index($request);
});
$router->get('/tasks', function (Request $request) use ($crm, $view, $now): void {
    (new TasksController($crm, $view, $now))->index($request);
});

$router->dispatch(Request::fromGlobals(), $_SERVER['REQUEST_METHOD'] ?? 'GET');
