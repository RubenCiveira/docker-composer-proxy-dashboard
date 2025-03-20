<?php

namespace RubenCiveiraiglesia\DockerDashboard\Model;

class Deployment {
    public string $name;
    public string $repositoryPath;
    public string $repositoryUrl;

    public array $mappers;

    public array $services;

    public static function from(array $data): Deployment {
        $deployment = new Deployment();
        $deployment->name = $data['name'];
        $deployment->repositoryPath = $data['repositoryPath'];
        $deployment->repositoryUrl = $data['repositoryUrl'];
        $deployment->mappers = $data['mappers'] ?? [];
        $deployment->services = $data['services'] ?? [];
        return $deployment;
    }
}
