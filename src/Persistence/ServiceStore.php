<?php

namespace RubenCiveiraiglesia\DockerDashboard\Persistence;

use RubenCiveiraiglesia\DockerDashboard\Config;
use RubenCiveiraiglesia\DockerDashboard\Domain\Infraservices\Postgresql;
use RubenCiveiraiglesia\DockerDashboard\Model\Service;

class ServiceStore extends Store {
    public function __construct(Config $config)
    {
        parent::__construct( $config->getDatabaseDir() . 'services.db' );
    }

    public function all(): array 
    {
        $all = parent::all();
        return array_map(fn($row) => Service::from($row),  $all);
    }

    public function get(string $key, mixed $mixed = null): mixed
    {
        $get = parent::get($key);
        return Service::from( $get );
    }
}