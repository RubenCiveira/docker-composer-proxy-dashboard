<?php
require '../vendor/autoload.php';

use RubenCiveiraiglesia\DockerDashboard\Driver\CredentialController;
use RubenCiveiraiglesia\DockerDashboard\Driver\DashboarController;
use RubenCiveiraiglesia\DockerDashboard\Driver\DeployController;
use RubenCiveiraiglesia\DockerDashboard\Driver\ServiceController;
use Slim\Factory\AppFactory;

$app = AppFactory::create();
$scriptName = $_SERVER['SCRIPT_NAME']; // Devuelve algo como "/midashboard/index.php"
$basePath = str_replace('/index.php', '', $scriptName); // "/midashboard"
$app->setBasePath($basePath);

$dashboardController = new DashboarController( $app->getBasePath() );
$dashboardController->bind( $app );

$deploymentsController = new DeployController($app->getBasePath() );
$deploymentsController->bind( $app );

$servicesController = new ServiceController($app->getBasePath() );
$servicesController->bind( $app );

$credentialController = new CredentialController($app->getBasePath() );
$credentialController->bind( $app );

$app->run();