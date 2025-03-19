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

    private string $database;

    private string $tempGitStore;

    private array $gitTokens;

    private array $registryTokens;

    public function __construct() {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->load();
        $this->clientId = $_ENV['GOOGLE_CLIENT_ID'];
        $this->clientSecret = $_ENV['GOOGLE_SECRET'];
        $this->redirectUri = $_ENV['GOOGLE_REDIRECT_URL'];
        $this->basedir = $_ENV['BASE_DIR'];
        $this->database = $_ENV['DATABASE'];
        $this->tempGitStore = $_ENV['TEMP_GIT_STORE'];
        $this->registryTokens = [];
        $this->gitTokens = [];
        $this->users = [];
        foreach($_ENV as $key=>$value) {
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

    public function getDatabaseFile() {
        return $this->database;
    }

    public function getTempGitStore() {
        return $this->tempGitStore;
    }

    public function getProxyDirectory($domain) {
        return $this->basedir . '/' . str_replace('.', '/', $domain) . '/';
    }

    public function googleConfig()
    {
        return [
            'clientId' => $this->clientId,
            'clientSecret' => $this->clientSecret,
            'redirectUri' => $this->redirectUri,
        ];
    }

    public function getValidUsers() {
        return $this->users;
    }

    public function getRegistriesCredentials() {
        return $this->registryTokens;
    }

    public function getAuthUrl(string $repoUrl): string {
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