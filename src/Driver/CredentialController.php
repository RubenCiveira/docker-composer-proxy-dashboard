<?php
namespace RubenCiveiraiglesia\DockerDashboard\Driver;

use RubenCiveiraiglesia\DockerDashboard\Domain\Security\Access;
use RubenCiveiraiglesia\DockerDashboard\Config;
use RubenCiveiraiglesia\DockerDashboard\Persistence\CredentialStore;
use Slim\App;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class CredentialController
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
        $app->get('/add-credential', [$this, 'showForm']);
        $app->get('/edit-credential/{name}', [$this, 'showForm']);
        $app->post('/add-credential', [$this, 'saveCredential']);
        $app->post('/edit-credential/{name}', [$this, 'saveCredential']);
    }

    public function saveCredential(Request $request, Response $response, array $args): Response
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
        $credential = $this->mapCredential($back, $request, $response);
        if( is_a($credential, Response::class) ) {
            return $credential;
        }
        if( !isset($name) ) {
            $name = $credential['name'];
        }
        $credentials = new CredentialStore($this->config);
        $credentials->set($name, $credential);
        // Mensaje de éxito
        $_SESSION['flash'] = [
            'type' => 'success',
            'message' => 'Service '.($creating ? 'creado' : 'modificado').' correctamente: ' . $credential['name']
        ];
        // Redirigir a la lista de deploys
        return $response->withHeader('Location', $root)->withStatus(302);
    }

    public function showForm(Request $request, Response $response, array $args): Response
    {
        $context = [];
        if( isset($args['name'])) {
            $credentials = new CredentialStore($this->config);
            $context['credential'] = $credentials->get( $args['name'] );
        }
        $response->getBody()->write( $this->template('credential-form.twig', $context ) );
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

    private function mapCredential(string $back, Request $request, Response $response): array|Response {
        $data = $request->getParsedBody();
        // Crear la estructura de la credencial
        $credential = [
            'name' => $data['name'],
            'scheme' => $data['scheme'],
            'username' => $data['username'],
            'password' => $data['password'],
            'service' => $data['service'],
            'path' => $data['path'],
            'params' => $data['params']
        ];
        return $credential;
    }

}