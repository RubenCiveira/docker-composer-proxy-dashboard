<?php
namespace RubenCiveiraiglesia\DockerDashboard\Driver;

use RubenCiveiraiglesia\DockerDashboard\Domain\Security\Access;
use RubenCiveiraiglesia\DockerDashboard\Config;
use RubenCiveiraiglesia\DockerDashboard\Persistence\ApplicationStore;
use Slim\App;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class DeployController
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
        $app->get('/add-deploy', [$this, 'showForm']);
        $app->get('/edit-deploy/{name}', [$this, 'showForm']);
        $app->post('/add-deploy', [$this, 'saveDeploy']);
        $app->post('/edit-deploy/{name}', [$this, 'saveDeploy']);
    }

    public function showForm(Request $request, Response $response, array $args): Response
    {
        $applications = new ApplicationStore($this->config);
        $context = [];
        if( isset( $args['name']) ) {
            $context['deploy'] = $applications->get( $args['name'] );
        }
        $response->getBody()->write( $this->template('deploy-form.twig', $context) );
        return $response;
    }

    public function saveDeploy(Request $request, Response $response, array $args): Response
    {
        $back = './edit-deploy';
        $root = './';
        $creating = true;
        if( isset($args['name']) ) {
            $name = $args['name']; // Obtiene el parámetro de la URL
            $back .=  $name;
            $root .= '..';
            $creating = false;
        }
        $deploy = $this->mapDeploy($back, $request, $response);
        // Obtener todos los datos POST
        if( is_a($deploy, Response::class) ) {
            return $deploy;
        }
        if( !isset($name) ) {
            $name = $deploy['name'];
        }
        $applications = new ApplicationStore($this->config);
        $applications->set($name, $deploy);
        // Mensaje de éxito
        $_SESSION['flash'] = [
            'type' => 'success',
            'message' => 'Deploy '.($creating ? 'creado' : 'modificado').' correctamente: ' . $deploy['name']
        ];
        // Redirigir a la lista de deploys
        return $response->withHeader('Location', $root )->withStatus(302);
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

    private function mapDeploy(string $back, Request $request, Response $response): array|Response {
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