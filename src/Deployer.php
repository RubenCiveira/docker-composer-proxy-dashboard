<?php

namespace RubenCiveiraiglesia\DockerDashboard;

use RubenCiveiraiglesia\DockerDashboard\Model\Deployment;

class Deployer {
    public function __construct(private readonly Config $config) {}

    public function deploy(Deployment $deployment) {
        $this->getFromGit( $deployment );
    }

    private function getFromGit(Deployment $deployment) {
        $directory = $this->config->getTempGitStore() .  '/' . md5($deployment->repositoryPath);
        $authUrl = $this->config->getAuthUrl($deployment->repositoryUrl);

        if (!is_dir($directory . '/.git')) {
            // Si el directorio no contiene un repositorio Git, clonarlo
            $this->runCommand('git clone', "git clone {$authUrl} {$directory} ");
        } else {
            // Si ya existe, actualizar la rama principal
            $this->runCommand('git pull', "cd {$directory} && git pull origin main");
        }
        $this->dockerLogin();
        // $this->runCommand("git clone {$deployment->repositoryPath} {$deployment->repositoryUrl}");
        $this->runCommand('docker compose', "cd {$directory}/{$deployment->repositoryPath}  && DOCKER_AUTH_CONFIG=$(cat ".$this->config->getTempGitStore()."/docker-auth.json) docker-compose up --force-recreate -d");
        $this->setupProxy($deployment, $directory . '/' . $deployment->repositoryPath);
    }

    private function dockerLogin(): void {
        foreach ($this->config->getRegistriesCredentials()  as $registry => $credentials) {
            [$username, $password] = explode(':', $credentials, 2);
            $auths[$registry] = [
                "auth" => base64_encode("$username:$password")
            ];
        }
        $config = [
            "auths" => $auths
        ];
        file_put_contents($this->config->getTempGitStore() . '/docker-auth.json', json_encode($config, JSON_PRETTY_PRINT));    }

    private function runCommand(string $label, string $command): void {
        echo "<p>Ejecutando: $label\n";
        $output = [];
        $returnVar = null;
        exec($command . ' 2>&1', $output, $returnVar);
        if ($returnVar !== 0) {
            echo "Error ejecutando comando:\n" . implode("\n", $output) . "\n";
            throw new \RuntimeException("Error ejecutando: $command");
        }
        echo "Resultado:\n" . implode("\n", $output) . "\n";
    }
    private function setupProxy(Deployment $deploy, string $directory): void {
        foreach( $deploy->mappers as $path => $port ) {
            $proxyDirectory = $this->config->getProxyDirectory($path);
            // Crear la carpeta si no existe
            if (!is_dir($proxyDirectory)) {
                mkdir($proxyDirectory, 0755, true);
            }
            // Copiar el archivo del proxy
            file_put_contents($proxyDirectory . 'index.php', $this->getProxyScript($port));
            // Copiar el .htaccess
            file_put_contents($proxyDirectory . '.htaccess', $this->getHtaccess());
            $this->copyFiles( $directory, $proxyDirectory );
        }
    }
    
    private function getProxyScript($port): string {
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
    
    private function getHtaccess(): string {
        return <<<HTACCESS
    RewriteEngine On
    
    # Redirigir todas las solicitudes al proxy PHP
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php [QSA,L]
    HTACCESS;
    }

    private function copyFiles(string $sourceDir, string $targetDir): void {
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
    private function recursiveCopy(string $source, string $destination): void {
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