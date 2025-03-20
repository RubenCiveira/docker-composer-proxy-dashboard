<?php

namespace RubenCiveiraiglesia\DockerDashboard\Model;

class Service
{
    public string $name;
    public string $image;
    public array $environment;
    public array $ports;
    public array $volumes;
    public string $kind;

    public static function from(array $data): Service {
        $service = new Service();
        $service->name = $data['name'];
        $service->image = $data['image'];
        $service->environment = $data['environment'] ?? [];
        $service->ports = $data['ports'] ?? [];
        $service->volumes = $data['volumes'] ?? [];
        $service->kind = $data['kind'];
        return $service;
    }

    // abstract public function bindCredential(string $role, string $user, string $password, string $path, array $params);

}