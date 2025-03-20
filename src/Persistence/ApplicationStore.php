<?php

namespace RubenCiveiraiglesia\DockerDashboard\Persistence;

use RubenCiveiraiglesia\DockerDashboard\Config;
use RubenCiveiraiglesia\DockerDashboard\Model\Deployment;

class ApplicationStore extends Store {
    public function __construct(Config $config)
    {
        parent::__construct( $config->getDatabaseDir() . 'applications.db' );
    }

    public function all(): array 
    {
        $all = parent::all();
        return array_map(fn($row) => Deployment::from($row),  $all);
    }

    public function get(string $key, mixed $mixed = null): mixed
    {
        $get = parent::get($key);
        return Deployment::from( $get );
    }
}