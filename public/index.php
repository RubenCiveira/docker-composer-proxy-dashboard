<?php
use RubenCiveiraiglesia\DockerDashboard\Access;
use RubenCiveiraiglesia\DockerDashboard\Config;
use RubenCiveiraiglesia\DockerDashboard\Deployer;
use RubenCiveiraiglesia\DockerDashboard\Model\Deployment;
use RubenCiveiraiglesia\DockerDashboard\Store;
use Slim\Psr7\Request;

require '../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Slim\Psr7\Response;

$app = AppFactory::create();

$config = new Config();
$access = new Access( $config );

$app->add(middleware: function (Request $request, $handler) use($access, $app) {
    $route = $request->getUri()->getPath(); // Obtiene la ruta actual
    $base = $app->getBasePath();
    $rutasPermitidas = [ $base . '/authorization-google', $base . '/callback'];
    // Si la ruta está permitida, se omite la autenticación
    if (in_array($route, $rutasPermitidas)) {
        return $handler->handle($request);
    }
    $user = $access->getUsername();
    if( !$user ) {
        $response = new Response();
        return $response->withHeader('Location', $access->getLocationToLogin() )->withStatus(302);
    } else if( !$access->isValidUser() ) {
        $response = new Response();
        $response->getBody()->write('El usuario ' . $user . 'no tiene acceso permitido');
        return $response->withStatus(403);
    } else {
        return $handler->handle($request);
    }
});

$app->get('/authorization-google', function (Request $request, Response $response) use ($access, $app) {
    $verified = $access->verifyGoogleCallback($request, $response);
    if( $verified ) {
        $base = $app->getBasePath();
        return $response->withHeader('Location', $base . '/')->withStatus(302);
    } else {
        $response->getBody()->write('Pendiente de verificar, ' );
        return $response->withStatus(403);
    }
});

// Rutas protegidas
$app->get('/', function (Request $request, Response $response) use ($config) {
    $store = new Store($config);

    $dep = new Deployment();
    $dep->name = 'Phylax';
    // $dep->domain = 'phylax';
    $dep->repositoryUrl = 'https://gitlab.com/phylax2/phylax-api-deploy/';
    $dep->repositoryPath = 'prod';
    $dep->mappers = [
        'phylax' => '8090'
    ];
    $store->set('Phylax', $dep);

    $all = $store->all();
    $response->getBody()->write('Bienvenido al Dashboard: <pre>' .  print_r( $all, true) . '</pre>'
        . '<p>Ve a <a href="./deploy/Phylax">Deploy</a>');
    return $response;
});

$app->get('/deploy/{name}', function(Request $request, Response $response, array $args) use ($config) {
    $name = $args['name']; // Obtiene el parámetro de la URL
    $store = new Store($config);
    $deployer = new Deployer($config);
    $deploy = Deployment::from( $store->get($name) );
    $deployer->deploy( $deploy );
    $response->getBody()->write('Desplegando');
    return $response;
});

$scriptName = $_SERVER['SCRIPT_NAME']; // Devuelve algo como "/midashboard/index.php"
$basePath = str_replace('/index.php', '', $scriptName); // "/midashboard"
$app->setBasePath($basePath);

$app->run();