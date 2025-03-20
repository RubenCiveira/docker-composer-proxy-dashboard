<?php

namespace RubenCiveiraiglesia\DockerDashboard\Domain\Command;

use RubenCiveiraiglesia\DockerDashboard\Config;

class GitRunner extends Runner
{
    public function __construct(private readonly Config $config)
    {
    }

    public function sync($reposityUrl, $repositoryPath): string
    {
        $directory = $this->config->getTempGitStore() . '/' . md5($repositoryPath);
        $authUrl = $this->config->getAuthUrl($reposityUrl);
        if (!is_dir("$directory/.git")) {
            // Si el directorio no contiene un repositorio Git, clonarlo
            $this->runCommand('git clone', "git clone {$authUrl} {$directory} ");
        } else {
            // Si ya existe, actualizar la rama principal
            $this->runCommand('git pull', "cd {$directory} && git pull origin main");
        }
        return $directory;
    }
}