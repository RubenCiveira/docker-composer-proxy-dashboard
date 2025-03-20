<?php

namespace RubenCiveiraiglesia\DockerDashboard\Domain\Command;

use RubenCiveiraiglesia\DockerDashboard\Config;

class DockerRunner extends Runner
{
    public function __construct(private readonly Config $config)
    {
        $this->dockerLogin();
    }

    public function runCompose(string $name, string $directory, null|string $file = null)
    {
        $this->runCommand("🚀 Levantando $name", "cd $directory  && DOCKER_AUTH_CONFIG=$(cat "
            . $this->config->getTempGitStore() .
            "/docker-auth.json) docker-compose " . ($file ? " -f  $file " : "") . " up --force-recreate -d");
    }
    private function dockerLogin(): void
    {
        foreach ($this->config->getRegistriesCredentials() as $registry => $credentials) {
            [$username, $password] = explode(':', $credentials, 2);
            $auths[$registry] = [
                "auth" => base64_encode("$username:$password")
            ];
        }
        $config = [
            "auths" => $auths
        ];
        file_put_contents($this->config->getTempGitStore() . '/docker-auth.json', json_encode($config, JSON_PRETTY_PRINT));
    }
}