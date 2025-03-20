<?php
namespace RubenCiveiraiglesia\DockerDashboard;

use Dotenv\Dotenv;

class Config
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private array $users;
    private string $basedir;
    private string $databaseDir;
    private string $tempGitStore;
    private string $dockerStore;
    private string $infraStore;
    private array $gitTokens;
    private array $registryTokens;

    public function __construct()
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->load();
        $this->clientId = $_ENV['GOOGLE_CLIENT_ID'];
        $this->clientSecret = $_ENV['GOOGLE_SECRET'];
        $this->redirectUri = $_ENV['GOOGLE_REDIRECT_URL'];
        $this->basedir = $_ENV['BASE_DIR'];
        $this->databaseDir = $_ENV['DATABASE_DIR'];
        $this->tempGitStore = $_ENV['TEMP_GIT_STORE'];
        $this->infraStore = $_ENV['INFRA_STORE'];
        $this->dockerStore = $_ENV['DOCKER_STORE'];
        $this->registryTokens = [];
        $this->gitTokens = [];
        $this->users = [];
        if( isset($_ENV['EXEC_PATH'])) {
            putenv('PATH='.$_ENV['EXEC_PATH']);
        }
        foreach ($_ENV as $key => $value) {
            if (preg_match('/^REGISTRY_LOGIN_(\d+)_URL$/', $key, $matches)) {
                $number = $matches[1]; // Captura el número en el medio
                $token = $_ENV['REGISTRY_LOGIN_' . $number . '_TOKEN'];
                $this->registryTokens[$value] = $token;
            }
            if (preg_match('/^GIT_LOGIN_(\d+)_URL$/', $key, $matches)) {
                $number = $matches[1]; // Captura el número en el medio
                $token = $_ENV['GIT_LOGIN_' . $number . '_TOKEN'];
                $this->gitTokens[$value] = $token;
            }
            if (preg_match('/^USERS_ALLOWED_(\d+)$/', $key, $matches)) {
                $this->users[] = $value;
            }
        }
    }

    public function getDatabaseDir(): string
    {
        return $this->databaseDir . '/';
    }

    public function getTempGitStore(): string
    {
        return $this->tempGitStore;
    }

    public function getDockerStore(): string
    {
        return $this->dockerStore;
    }

    public function getInfraStrore(): string
    {
        return $this->infraStore;
    }
    public function getProxyDirectory($domain): string
    {
        return $this->basedir . '/' . str_replace('.', '/', $domain) . '/';
    }

    public function googleVerificationUrl(): string
    {
        return '/' . basename($this->redirectUri);
    }
    public function googleConfig(): array
    {
        return [
            'clientId' => $this->clientId,
            'clientSecret' => $this->clientSecret,
            'redirectUri' => $this->redirectUri,
        ];
    }

    public function getValidUsers(): array
    {
        return $this->users;
    }

    public function getRegistriesCredentials(): array
    {
        return $this->registryTokens;
    }

    public function getAuthUrl(string $repoUrl): string
    {
        // Extraer el dominio del repositorio (gitlab.com, github.com, etc.)
        $parsedUrl = parse_url($repoUrl);
        $host = $parsedUrl['host'] ?? '';

        // Obtener credenciales del host
        $credentials = $this->gitTokens[$host] ?? null;
        if (!$credentials) {
            throw new \RuntimeException("❌ No hay credenciales para $host.");
        }

        // Construir la URL con autenticación
        return str_replace("://", "://$credentials@", $repoUrl);
    }
}