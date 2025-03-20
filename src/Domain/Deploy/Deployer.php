<?php

namespace RubenCiveiraiglesia\DockerDashboard\Domain\Deploy;

use RubenCiveiraiglesia\DockerDashboard\Config;
use RubenCiveiraiglesia\DockerDashboard\Domain\Command\DockerRunner;
use RubenCiveiraiglesia\DockerDashboard\Domain\Command\GitRunner;
use RubenCiveiraiglesia\DockerDashboard\Model\Deployment;
use RubenCiveiraiglesia\DockerDashboard\Persistence\CredentialStore;
use RubenCiveiraiglesia\DockerDashboard\Persistence\ServiceStore;
use Symfony\Component\Yaml\Yaml;

class Deployer
{
    private readonly DockerRunner $docker;
    private readonly GitRunner $git;
    public function __construct(
        private readonly Config $config,
        private readonly CredentialStore $credentials,
        private readonly ServiceStore $services,
        private readonly InfrastructureDeployer $infra
    ) {
        $this->docker = new DockerRunner($config);
        $this->git = new GitRunner($config);
    }

    public function deploy(Deployment $deployment): void
    {
        $this->infra->deployInfrastructure();
        // Tengo que añadirle los valores de claves intermedios.
        $local = $this->git->sync($deployment->repositoryUrl, $deployment->repositoryPath);
        $directory = $this->dumpDocker($local, $deployment);
        // Tengo que copiar en un subdirec
        $this->assigEnv($directory, $deployment);
        $this->docker->runCompose("Application $deployment->name", "$directory");
        $this->setupProxy($deployment, "$directory");
    }

    private function dumpDocker(string $directory, Deployment $deployment): string
    {
        $target = $this->config->getDockerStore() . '/' . $deployment->name;
        if (!is_dir($target)) {
            mkdir($target, 0755, true);
        }
        $this->copyFiles("$directory/$deployment->repositoryPath", $target);
        return $target;
    }

    private function assigEnv(string $directory, Deployment $deployment)
    {
        $append = false;
        $vars = [];
        foreach ($deployment->services as $on => $services) {
            $service = $this->services->get($on);
            if (!$service) {
                throw new \Error("Servicio $on desconocido");
            }
            foreach ($services as $serviceName => $credentials) {
                $vars[$serviceName] = [];
                foreach ($credentials as $credential => $enviroment) {
                    $value = $this->credentials->get($credential);
                    $parts = parse_url($value);
                    $params = [];
                    if (isset($parsed['query'])) {
                        parse_str($parts['query'], $params);
                    }
                    foreach ($enviroment as $k => $v) {
                        $append = true;
                        switch ($k) {
                            case 'host':
                                $vars[$serviceName][$v] = $on;
                                break;
                            case 'port':
                                $port = "";
                                foreach ($service->ports as $p => $i) {
                                    $port = $p;
                                }
                                $vars[$serviceName][$v] = $port;
                                break;
                            case 'user':
                                $vars[$serviceName][$v] = $parts['user'];
                                break;
                            case 'pass':
                                $vars[$serviceName][$v] = $parts['pass'];
                                break;
                            case 'path':
                                $vars[$serviceName][$v] = $parts['path'] ? substr($parts['path'], 1) : '';
                                break;
                            default:
                                $vars[$serviceName][$v] = $params[$k] ?? "";
                        }
                    }
                }
                if ($append) {
                    $dockerCompose = Yaml::parseFile($directory . '/docker-compose.yml');
                    foreach ($dockerCompose['services'] as $serviceName => $service) {
                        if( isset($vars[$serviceName]) ) {
                            if( !isset($service['environment'])) {
                                $service['environment'] = [];
                            }
                            foreach($vars[$serviceName] as $k=>$v) {
                                $service['environment'][$k] = $v;
                            }
                            $dockerCompose['services'][$serviceName] = $service;
                        }
                    }
                    file_put_contents($directory . '/docker-compose.yml', Yaml::dump($dockerCompose, 4, 2, 
                        Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_OBJECT_AS_MAP));
                }
            }
        }
    }

    private function setupProxy(Deployment $deploy, string $directory): void
    {
        foreach ($deploy->mappers as $path => $port) {
            $proxyDirectory = $this->config->getProxyDirectory($path);
            // Crear la carpeta si no existe
            if (!is_dir($proxyDirectory)) {
                mkdir($proxyDirectory, 0755, true);
            }
            // Copiar el archivo del proxy
            file_put_contents($proxyDirectory . 'index.php', $this->getProxyScript($port));
            // Copiar el .htaccess
            file_put_contents($proxyDirectory . '.htaccess', $this->getHtaccess());
            $this->copyFiles($directory, $proxyDirectory);
        }
    }

    private function getProxyScript($port): string
    {
        return <<<PHP
    <?php
    \$target_host = 'localhost';  // Host del servicio dentro del contenedor
    \$target_port = $port;         // Puerto donde corre la aplicación en Docker
    
    function is_service_available(\$host, \$port) {
        \$socket = @fsockopen(\$host, $port, \$errno, \$errstr, 1);
        if (\$socket) {
            fclose(\$socket);
            return true;
        }
        return false;
    }

    // Función para iniciar el contenedor si está apagado
    function start_container() {
        shell_exec("docker-compose up -d");
        sleep(1);  // Espera para que el servicio arranque correctamente
    }

    // ** PRIMER INTENTO DE CONEXIÓN **
    \$connected = is_service_available(\$target_host, \$target_port);

    // ** SI FALLA, INTENTA ARRANCAR EL CONTENEDOR Y REINTENTAR **
    if (!\$connected) {
        start_container();
        \$connected = is_service_available(\$target_host, \$target_port);
    }

    // ** SI AÚN FALLA, DEVOLVER ERROR 503 **
    if (!\$connected) {
        http_response_code(503);
        exit("Servicio no disponible.");
    }

    // ** Manejo de WebSockets **
    if (isset(\$_SERVER['HTTP_UPGRADE']) && strtolower(\$_SERVER['HTTP_UPGRADE']) === 'websocket') {
        \$ws = stream_socket_client("tcp://\$target_host:\$target_port", \$errno, \$errstr, 30);
        if (!\$ws) {
            http_response_code(500);
            exit("Error conectando a WebSocket: \$errstr (\$errno)");
        }
    
        // Redirigir tráfico entre el cliente y el WebSocket del backend
        \$client = fopen('php://input', 'r');
        stream_copy_to_stream(\$client, \$ws);
        stream_copy_to_stream(\$ws, fopen('php://output', 'w'));
        fclose(\$ws);
        exit;
    }
    
    \$headers = getallheaders();
    \$headers["X-Forwarded-For"] = \$_SERVER['REMOTE_ADDR'];
    \$headers["X-Forwarded-Host"] = \$_SERVER['HTTP_HOST'];
    \$headers["X-Forwarded-Proto"] = isset(\$_SERVER['HTTPS']) ? 'https' : 'http';

    // ** Manejo de HTTP normal **
    \$ch = curl_init();
    \$scriptName = \$_SERVER['SCRIPT_NAME']; // Devuelve algo como "/midashboard/index.php"
    \$basePath = str_replace('/index.php', '', \$scriptName); // "/midashboard"
    \$localPath = str_replace(\$basePath, '', \$_SERVER['REQUEST_URI']);
    curl_setopt(\$ch, CURLOPT_URL, "http://\$target_host:\$target_port" . \$localPath);
    curl_setopt(\$ch, CURLOPT_HEADER, true);
    curl_setopt(\$ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt(\$ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt(\$ch, CURLOPT_HTTPHEADER, array_map(
        fn(\$k, \$v) => "\$k: \$v", array_keys(\$headers), \$headers
    ));
    curl_setopt(\$ch, CURLOPT_CUSTOMREQUEST, \$_SERVER['REQUEST_METHOD']);
    curl_setopt(\$ch, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
    
    \$response = curl_exec(\$ch);
    \$http_code = curl_getinfo(\$ch, CURLINFO_HTTP_CODE);
    \$header_size = curl_getinfo(\$ch, CURLINFO_HEADER_SIZE);
    \$header_text = substr(\$response, 0, \$header_size);
    \$body = substr(\$response, \$header_size);
    curl_close(\$ch);

    foreach (explode("\\r\\n", \$header_text) as \$header) {
        if (!empty(\$header) && !preg_match('/^HTTP\/\d/', \$header)) {
            header(\$header);
        }
    }
    http_response_code(\$http_code);
    echo \$body;
    PHP;
    }

    private function getHtaccess(): string
    {
        return <<<HTACCESS
    RewriteEngine On
    
    # Redirigir todas las solicitudes al proxy PHP
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php [QSA,L]
    HTACCESS;
    }

    private function copyFiles(string $sourceDir, string $targetDir): void
    {
        if (!is_dir($sourceDir)) {
            throw new \Exception("Error: El directorio del repositorio ($sourceDir) no existe.");
        }

        // Usar rsync si está disponible (más eficiente)
        if (shell_exec("which rsync")) {
            shell_exec("rsync -a --exclude='.git' $sourceDir/ $targetDir/");
            return;
        }

        // Si no hay rsync, usar copy manualmente
        $this->recursiveCopy($sourceDir, $targetDir);
    }

    /**
     * Copia archivos y carpetas recursivamente.
     */
    private function recursiveCopy(string $source, string $destination): void
    {
        $dir = opendir($source);
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..' || $file === '.git') {
                continue; // Saltar directorios especiales y .git
            }

            $srcPath = $source . DIRECTORY_SEPARATOR . $file;
            $destPath = $destination . DIRECTORY_SEPARATOR . $file;

            if (is_dir($srcPath)) {
                $this->recursiveCopy($srcPath, $destPath);
            } else {
                copy($srcPath, $destPath);
            }
        }
        closedir($dir);
    }
}