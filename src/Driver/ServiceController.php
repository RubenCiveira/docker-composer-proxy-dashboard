<?php
namespace RubenCiveiraiglesia\DockerDashboard\Driver;

use RubenCiveiraiglesia\DockerDashboard\Domain\Security\Access;
use RubenCiveiraiglesia\DockerDashboard\Config;
use RubenCiveiraiglesia\DockerDashboard\Persistence\ServiceStore;
use Slim\App;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class ServiceController
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
        $app->get('/add-service', [$this, 'showForm']);
        $app->get('/edit-service/{name}', [$this, 'showForm']);
        $app->post('/add-service', [$this, 'saveService']);
        $app->post('/edit-service/{name}', [$this, 'saveService']);
    }

    public function saveService(Request $request, Response $response, array $args): Response
    {
        $back = '';
        $root = './';
        $creating = true;
        if( isset($args['name'])) {
            $name = $args['name']; // Obtiene el parámetro de la URL
            $back .= $name;
            $root .= '..';
            $creating = false;
        }

        // Obtener todos los datos POST
        $service = $this->mapService($back, $request, $response);
        if( is_a($service, Response::class) ) {
            return $service;
        }
        if( !isset($name) ) {
            $name = $service['name'];
        }
        $services = new ServiceStore($this->config);
        $services->set($name, $service);
        // Mensaje de éxito
        $_SESSION['flash'] = [
            'type' => 'success',
            'message' => 'Service '.($creating ? 'creado' : 'modificado').' correctamente: ' . $service['name']
        ];
        // Redirigir a la lista de deploys
        return $response->withHeader('Location', $root)->withStatus(302);
    }

    public function showForm(Request $request, Response $response, array $args): Response
    {
        $context = [
            'serviceTypes' => ['postgres', 'mysql', 'redis', 'mongodb', 'nginx', 'apache']
        ];
        if( isset($args['name'])) {
            $services = new ServiceStore($this->config);
            $context['service'] = $services->get( $args['name'] );
        }
        $response->getBody()->write( $this->template('service-form.twig', $context ) );
        return $response;
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

    private function mapService(string $back, Request $request, Response $response): array|Response {
        $data = $request->getParsedBody();
        // Transformar los datos del formulario a la estructura esperada
        $service = [
            'name' => $data['name'],
            'image' => $data['image'],
            'kind' => $data['kind'],
            'environment' => [],
            'ports' => [],
            'volumes' => []
        ];
        
        // Procesar variables de entorno
        if (isset($data['environment']['key']) && isset($data['environment']['value'])) {
            foreach ($data['environment']['key'] as $index => $key) {
                if (!empty($key)) {
                    $service['environment'][$key] = $data['environment']['value'][$index];
                }
            }
        }
        
        // Procesar puertos
        if (isset($data['ports']['host']) && isset($data['ports']['container'])) {
            foreach ($data['ports']['host'] as $index => $host) {
                if (!empty($host)) {
                    $service['ports'][$host] = $data['ports']['container'][$index];
                }
            }
        }
        return $service;
    }

}