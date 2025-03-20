<?php
require '../vendor/autoload.php';

use RubenCiveiraiglesia\DockerDashboard\Driver\DashboarController;
use Slim\Factory\AppFactory;

$app = AppFactory::create();
$scriptName = $_SERVER['SCRIPT_NAME']; // Devuelve algo como "/midashboard/index.php"
$basePath = str_replace('/index.php', '', $scriptName); // "/midashboard"
$app->setBasePath($basePath);

$dashboardController = new DashboarController( $app->getBasePath() );
$dashboardController->bind( $app );

$app->run();