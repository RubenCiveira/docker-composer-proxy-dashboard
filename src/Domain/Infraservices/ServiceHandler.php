<?php

namespace RubenCiveiraiglesia\DockerDashboard\Domain\Infraservices;

use RubenCiveiraiglesia\DockerDashboard\Model\Service;

interface ServiceHandler
{
    public function bindCredential(Service $service, string $role, string $user, string $password, string $path, array $params);

    public function handleService(Service $service): bool;
}