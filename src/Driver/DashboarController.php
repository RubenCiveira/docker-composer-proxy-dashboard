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
        $app->get('/add-deploy', [$this, 'showAddDeploy']);
        $app->post('/add-deploy', [$this, 'addDeploy']);
        $app->get('/edit-deploy/{name}', [$this, 'showEditDeploy']);
        $app->post('/edit-deploy/{name}', [$this, 'editDeploy']);
        $app->get('/deploy/{name}', [$this, 'deploy']);
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

    public function addDeploy(Request $request, Response $response): Response
    {
        // Obtener todos los datos POST
        $deploy = $this->map('./add-deploy', $request, $response);
        if( is_a($deploy, Response::class) ) {
            return $deploy;
        }
        $applications = new ApplicationStore($this->config);
        $applications->set($deploy['name'], $deploy);
        // Mensaje de éxito
        $_SESSION['flash'] = [
            'type' => 'success',
            'message' => 'Deploy creado correctamente: ' . $deploy['name']
        ];
        // Redirigir a la lista de deploys
        return $response->withHeader('Location', './')->withStatus(302);
    }

    public function editDeploy(Request $request, Response $response, array $args): Response
    {
        $name = $args['name']; // Obtiene el parámetro de la URL

        // Obtener todos los datos POST
        $deploy = $this->map('./edit-deploy/' . $name, $request, $response);
        if( is_a($deploy, Response::class) ) {
            return $deploy;
        }

        $applications = new ApplicationStore($this->config);
        $applications->set($name, $deploy);
        // Mensaje de éxito
        $_SESSION['flash'] = [
            'type' => 'success',
            'message' => 'Deploy creado correctamente: ' . $deploy['name']
        ];
        // Redirigir a la lista de deploys
        return $response->withHeader('Location', './' . $name)->withStatus(302);
    }

    public function showAddDeploy(Request $request, Response $response): Response
    {
        $response->getBody()->write( $this->template('add-deploy.twig', []) );
        return $response;
    }

    public function showEditDeploy(Request $request, Response $response, array $args): Response
    {
        $name = $args['name']; // Obtiene el parámetro de la URL
        $applications = new ApplicationStore($this->config);
        $app = $applications->get( $name );
        $response->getBody()->write( $this->template('add-deploy.twig', ['deploy' => $app]) );
        return $response;
    }

    public function index(Request $request, Response $response): Response
    {
        $applications = new ApplicationStore($this->config);
        $credentials = new CredentialStore($this->config);
        $services = new ServiceStore($this->config);
        $this->init( $applications, $services, $credentials);
        $allApps = $applications->all();
        $allServs = $services->all();
        $allCreds = $credentials->all();

        $response->getBody()->write($this->template('dashboard.twig', [
            'applications' => $allApps,
            'services' => $allServs,
            'credentials' => $allCreds,
        ]));
        // $response->getBody()->write('Bienvenido al Dashboard: <table><tr><td><pre>' . print_r(value: $allApps, return: true) . '</pre></td>'
        //      . '<td><pre>' . print_r($allServs, true) . '</pre></td></tr></table>'
        //      . '<p>Ve a <a href="./deploy/Phylax">Deploy</a>');
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

    private function map(string $back, Request $request, Response $response): array|Response {
        $postData = $request->getParsedBody();
            
        // Validar datos básicos
        if (empty($postData['name']) || empty($postData['repositoryUrl']) || empty($postData['repositoryPath'])) {
            // Redirigir con mensaje de error
            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => 'Los campos nombre, URL del repositorio y ruta del repositorio son obligatorios.'
            ];
            return $response->withHeader('Location', $back)->withStatus(302);
        }
            // Inicializar la estructura del deploy
        $deploy = [
            'name' => $postData['name'],
            'repositoryUrl' => $postData['repositoryUrl'],
            'repositoryPath' => $postData['repositoryPath'],
            'mappers' => [],
            'services' => []
        ];
        
        // Procesar mappers (puertos)
        if (isset($postData['mappers']) && isset($postData['mappers']['app']) && isset($postData['mappers']['port'])) {
            $appNames = $postData['mappers']['app'];
            $ports = $postData['mappers']['port'];
            
            foreach ($appNames as $index => $appName) {
                if (!empty($appName) && isset($ports[$index]) && !empty($ports[$index])) {
                    $deploy['mappers'][$appName] = $ports[$index];
                }
            }
        }
        
        // Procesar servicios y credenciales
        if (isset($postData['services']) && isset($postData['services']['name'])) {
            $serviceNames = $postData['services']['name'];
            
            foreach ($serviceNames as $serviceIndex => $serviceName) {
                if (empty($serviceName)) continue;
                
                // Inicializar el servicio
                $deploy['services'][$serviceName] = [];
                
                // Procesar aplicaciones para este servicio
                if (isset($postData['services'][$serviceIndex]['app'])) {
                    $appNames = $postData['services'][$serviceIndex]['app'];
                    
                    foreach ($appNames as $appIndex => $appName) {
                        if (empty($appName)) continue;
                        
                        // Inicializar la aplicación
                        $deploy['services'][$serviceName][$appName] = [];
                        
                        // Procesar credenciales para esta aplicación
                        if (isset($postData['services'][$serviceIndex][$appIndex]['cred'])) {
                            $credNames = $postData['services'][$serviceIndex][$appIndex]['cred'];
                            
                            foreach ($credNames as $credIndex => $credName) {
                                if (empty($credName)) continue;
                                
                                // Inicializar la credencial
                                $deploy['services'][$serviceName][$appName][$credName] = [];
                                
                                // Procesar mapeos de propiedades a variables de entorno
                                if (isset($postData['services'][$serviceIndex][$appIndex][$credIndex]['prop']) && 
                                    isset($postData['services'][$serviceIndex][$appIndex][$credIndex]['env'])) {
                                    
                                    $propNames = $postData['services'][$serviceIndex][$appIndex][$credIndex]['prop'];
                                    $envNames = $postData['services'][$serviceIndex][$appIndex][$credIndex]['env'];
                                    
                                    foreach ($propNames as $mapIndex => $propName) {
                                        if (!empty($propName) && isset($envNames[$mapIndex]) && !empty($envNames[$mapIndex])) {
                                            $deploy['services'][$serviceName][$appName][$credName][$propName] = $envNames[$mapIndex];
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        return $deploy;
    }
}