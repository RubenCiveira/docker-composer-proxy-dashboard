<?php

namespace RubenCiveiraiglesia\DockerDashboard\Domain\Security;

use RubenCiveiraiglesia\DockerDashboard\Config;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use League\OAuth2\Client\Provider\Google;

session_start();

class Access
{
    private readonly Google $provider;
    private readonly array $users;
    private readonly string $verifyEndpoint;
    public function __construct(Config $config)
    {
        $this->provider = new Google($config->googleConfig());
        $this->users = $config->getValidUsers();
        $this->verifyEndpoint = $config->googleVerificationUrl();
    }

    public function getUsername(): mixed
    {
        return isset($_SESSION['user']['name']) ? $_SESSION['user']['name'] : null;
    }

    public function isValidUser(): bool
    {
        $email = isset($_SESSION['user']['email']) ? $_SESSION['user']['email'] : null;
        return $email && in_array($email, $this->users);
    }

    public function getVerificationEndpoint() : string
    {
        return $this->verifyEndpoint;
    }

    public function getLocationToLogin(): string
    {
        // No autenticado, redirigir a Google
        $authUrl = $this->provider->getAuthorizationUrl();
        $_SESSION['oauth2state'] = $this->provider->getState();
        return $authUrl;
    }

    public function verifyGoogleCallback(Request $request, Response $response): bool
    {
        $queryParams = $request->getQueryParams();

        if (!isset($queryParams['code']) || !isset($queryParams['state']) || $queryParams['state'] !== $_SESSION['oauth2state']) {
            unset($_SESSION['oauth2state']);
            $response->getBody()->write('Error de autenticación.');
            return false;
            // return $response->withStatus(400);
        }

        $token = $this->provider->getAccessToken('authorization_code', ['code' => $queryParams['code']]);
        $user = $this->provider->getResourceOwner($token);
        $email = $user->getEmail();

        if (!in_array($email, $this->users)) {
            return false;
            // return $response->withStatus(403)->write('Acceso denegado');
        } else {
            $_SESSION['user'] = ['name' => $user->getName(), 'email' => $email];
            return true;
            // return $response->withHeader('Location', '/dashboard')->withStatus(302);
        }
    }
}