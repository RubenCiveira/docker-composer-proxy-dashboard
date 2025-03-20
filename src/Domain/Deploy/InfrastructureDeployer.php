<?php

namespace RubenCiveiraiglesia\DockerDashboard\Domain\Deploy;

use RubenCiveiraiglesia\DockerDashboard\Config;
use RubenCiveiraiglesia\DockerDashboard\Domain\Command\DockerRunner;
use RubenCiveiraiglesia\DockerDashboard\Domain\Infraservices\Handlers;
use RubenCiveiraiglesia\DockerDashboard\Persistence\ApplicationStore;
use RubenCiveiraiglesia\DockerDashboard\Persistence\CredentialStore;
use RubenCiveiraiglesia\DockerDashboard\Persistence\ServiceStore;

use Symfony\Component\Yaml\Yaml;

class InfrastructureDeployer
{
    private readonly DockerRunner $docker;
    public function __construct(
        private readonly Config $config,
        private readonly ServiceStore $store,
        private readonly ApplicationStore $applications,
        private readonly CredentialStore $credentials,
        private readonly string $infraPath
    ) {
        $this->docker = new DockerRunner($config);
    }
    public function deployInfrastructure(): void
    {
        if (!is_dir($this->infraPath)) {
            mkdir($this->infraPath, 0755, true);
        }
        $all = $this->applications->all();
        $requiredServices = $this->getRequiredServices($all);
        $dockerComposeContent = $this->generateDockerCompose($requiredServices);

        // Guardar el archivo docker-compose.infrastructure.yml
        file_put_contents($this->infraPath . '/docker-compose.infrastructure.yml', $dockerComposeContent);
        $this->startInfrastructure();
        sleep(2);
        $this->assignCredentials();
    }

    private function assignCredentials()
    {
        $handlers = new Handlers();
        $all = $this->credentials->all();
        $allServices = $this->store->all();
        $allHandlers = $handlers->all();
        foreach ($all as $cred) {
            $parts = parse_url($cred);
            $on = $parts['host'];
            $params = [];
            if (isset($parsed['query'])) {
                parse_str($parts['query'], $params);
            }
            foreach ($allServices as $service) {
                if ($service->name == $on) {
                    $handled = false;
                    foreach($allHandlers as $handler) {
                        if( $handler->handleService($service) ) {
                            $handler->bindCredential($service, 
                                $parts['scheme'], 
                                $parts['user'] ?? '', $parts['pass'] ?? '', $parts['path'] ? substr($parts['path'],1) : '', $params);
                            $handled = true;
                        }
                    }
                    if( !$handled ) {
                        echo "<h1>Error, no handler for $service->name</h1>";
                    }
                }
            }
        }
    }

    private function getRequiredServices(array $applications): array
    {
        $allServices = $this->store->all();
        $availableServices = [];
        foreach ($allServices as $service) {
            $availableServices[$service->name] = $service;
        }
        $services = [];

        foreach ($applications as $app) {
            foreach ($app->services as $service => $the_envs) {
                if (isset($availableServices[$service])) {
                    $available = $availableServices[$service];
                    $serviceData = [
                        'image' => $available->image,
                        'container_name' => $available->name,
                        'environment' => $available->environment,
                        'ports' => $this->merge($available->ports),
                        'volumes' => $this->merge($available->volumes)
                    ];
                    $services[$service] = $serviceData;
                }
            }
        }
        return $services;
    }

    private function generateDockerCompose(array $services): string
    {
        $dockerCompose = [
            'name' => 'infrastructure',
            'services' => $services
        ];
        return Yaml::dump($dockerCompose, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_OBJECT_AS_MAP);
    }

    private function startInfrastructure(): void
    {
        $this->docker->runCompose("infraestructura....", $this->infraPath, "docker-compose.infrastructure.yml");
    }

    private function merge(array $vcc): array
    {
        $merged = [];
        foreach ($vcc as $k => $v) {
            $merged[] = "$k:$v";
        }
        return $merged;
    }
}