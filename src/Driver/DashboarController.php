<?php

namespace RubenCiveiraiglesia\DockerDashboard\Driver;

use Psr\Http\Server\RequestHandlerInterface;
use RubenCiveiraiglesia\DockerDashboard\Domain\Security\Access;
use RubenCiveiraiglesia\DockerDashboard\Config;
use RubenCiveiraiglesia\DockerDashboard\Domain\Deploy\Deployer;
use RubenCiveiraiglesia\DockerDashboard\Domain\Deploy\InfrastructureDeployer;
use RubenCiveiraiglesia\DockerDashboard\Model\Deployment;
use RubenCiveiraiglesia\DockerDashboard\Model\Service;
use RubenCiveiraiglesia\DockerDashboard\Persistence\ApplicationStore;
use RubenCiveiraiglesia\DockerDashboard\Persistence\CredentialStore;
use RubenCiveiraiglesia\DockerDashboard\Persistence\ServiceStore;
use Slim\App;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class DashboarController
{
    private readonly Config $config;
    private readonly Access $access;
    public function __construct(private readonly string $basePath)
    {
        $this->config = new Config();
        $this->access = new Access($this->config);
    }

    public function bind(App $app)
    {
        $app->add(middleware: [$this, 'authorize']);
        $app->get($this->getVerifyAuthorizationLocation(), [$this, 'verifyAuthorization']);
        
        $app->get('/', [$this, 'index']);
        $app->get('/deploy/{name}', [$this, 'deploy']);

        // $app->get('/add-service', [$this, 'showAddService']);
        // $app->post('/add-service', [$this, 'addService']);
        // $app->get('/edit-service/{name}', [$this, 'showEditService']);
        // $app->post('/edit-service/{name}', [$this, 'editService']);
    }

    public function authorize(Request $request, RequestHandlerInterface $handler): Response
    {
        $route = $request->getUri()->getPath(); // Obtiene la ruta actual
        $rutasPermitidas = [$this->basePath . $this->getVerifyAuthorizationLocation()];
        // Si la ruta está permitida, se omite la autenticación
        if (in_array($route, $rutasPermitidas)) {
            return $handler->handle($request);
        }
        $user = $this->access->getUsername();
        if (!$user) {
            $response = new Response();
            return $response->withHeader('Location', $this->access->getLocationToLogin())->withStatus(302);
        } else if (!$this->access->isValidUser()) {
            $response = new Response();
            $response->getBody()->write("El usuario $user no tiene acceso permitido");
            return $response->withStatus(403);
        } else {
            return $handler->handle($request);
        }
    }

    public function getVerifyAuthorizationLocation(): string
    {
        return $this->access->getVerificationEndpoint();
    }

    public function verifyAuthorization(Request $request, Response $response): Response
    {
        $verified = $this->access->verifyGoogleCallback($request, $response);
        if ($verified) {
            return $response->withHeader('Location', "$this->basePath/")->withStatus(302);
        } else {
            $response->getBody()->write('Pendiente de verificar, ');
            return $response->withStatus(403);
        }
    }

    public function index(Request $request, Response $response): Response
    {
        $applications = new ApplicationStore($this->config);
        $credentials = new CredentialStore($this->config);
        $services = new ServiceStore($this->config);
        // $this->init( $applications, $services, $credentials);
        $allApps = $applications->all();
        $allServs = $services->all();
        $allCreds = $credentials->all();

        $response->getBody()->write($this->template('dashboard.twig', [
            'applications' => $allApps,
            'services' => $allServs,
            'credentials' => $allCreds,
        ]));
        return $response;
    }

    public function deploy(Request $request, Response $response, array $args): Response
    {
        $name = $args['name']; // Obtiene el parámetro de la URL
        $applications = new ApplicationStore($this->config);
        $credentials = new CredentialStore($this->config);
        $services = new ServiceStore($this->config);
        $infra = new InfrastructureDeployer($this->config, $services, $applications, $credentials, $this->config->getInfraStrore() );
        $deployer = new Deployer($this->config, $credentials, $services, $infra);
        // $deploy = Deployment::from( $store->get($name) );
        $deploy = $applications->get($name);
        $deployer->deploy($deploy);
        $response->getBody()->write('Desplegando');
        return $response;
    }

    private function init(ApplicationStore $applications, ServiceStore $services, CredentialStore $credentials) {
        $dep = new Deployment();
        $dep->name = 'Phylax';
        // $dep->domain = 'phylax';
        $dep->repositoryUrl = 'https://gitlab.com/phylax2/phylax-api-deploy/';
        $dep->repositoryPath = 'prod';
        $dep->mappers = [
            'phylax' => '8090'
        ];
        $dep->services = [
            'postgresql' => [
                'phylax-api' => [
                    'nico' => [
                        'host' => 'PG_HOST',
                        'port' => 'PG_PORT',
                        'user' => 'PG_USER',
                        'pass' => 'PG_PASS',
                        'path' => 'PG_PATH',
                        'schema' => 'PG_SCHEMA'
                        ]
                ]
            ]
        ];
        $applications->set($dep->name, $dep);

        $credentials->set('pico', 'owner://test_write:test@postgresql/test-phylax?schema=public');
        $credentials->set('nico', 'owner://test_write:test@postgresql/colorida?schema=public');

        $serv = new Service();
        $serv->name = 'postgresql';
        $serv->image = 'postgres:17';
        $serv->ports = ['15432' => '5432'];
        $serv->kind = 'postgres';
        $serv->environment = [
                'POSTGRES_USER' => 'root',
                'POSTGRES_PASSWORD' => 'root',
                'POSTGRES_DB' => 'global_db' ];
        $serv->volumes = [
            './data/postgresql' => '/var/lib/postgresql/data'
        ];
        $services->set($serv->name, $serv);
    }

    private function template(string $name, array $context): string {
        if( isset( $_SESSION['flash'] )) {
            $context['flash'] = $_SESSION['flash'];
            unset( $_SESSION['flash'] );
        }
        $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../../templates');
        $twig = new \Twig\Environment($loader, [
            'cache' => __DIR__ . '/../../cache',
            'debug' => true,
        ]);
        // Renderizar la plantilla con los datos
        $html = $twig->render($name, $context);
        return $html;
    }
}