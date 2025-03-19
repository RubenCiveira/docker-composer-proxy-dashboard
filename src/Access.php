<?php

namespace RubenCiveiraiglesia\DockerDashboard;

use Slim\Psr7\Request;
use Slim\Psr7\Response;
use League\OAuth2\Client\Provider\Google;

session_start();

class Access
{
    private readonly Google $provider;
    private readonly array $users;
    public function __construct(Config $config)
    {
        $this->provider = new Google($config->googleConfig());
        $this->users = $config->getValidUsers();
    }

    public function getUsername()
    {
        return isset($_SESSION['user']['name']) ? $_SESSION['user']['name'] : null;
    }

    public function isValidUser() {
        $email = isset($_SESSION['user']['email']) ? $_SESSION['user']['email'] : null;
        return $email && in_array($email, $this->users);
    }

    public function getLocationToLogin()
    {
        // No autenticado, redirigir a Google
        $authUrl = $this->provider->getAuthorizationUrl();
        $_SESSION['oauth2state'] = $this->provider->getState();
        return $authUrl;
    }

    public function verifyGoogleCallback(Request $request, Response $response)
    {
        $queryParams = $request->getQueryParams();

        if (!isset($queryParams['code']) || !isset($queryParams['state']) || $queryParams['state'] !== $_SESSION['oauth2state']) {
            unset($_SESSION['oauth2state']);
            $response->getBody()->write('Error de autenticación.');
            return $response->withStatus(400);
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