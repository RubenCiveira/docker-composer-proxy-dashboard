<?php

namespace RubenCiveiraiglesia\DockerDashboard\Persistence;

use RubenCiveiraiglesia\DockerDashboard\Config;

class CredentialStore extends Store {
    public function __construct(Config $config)
    {
        parent::__construct( $config->getDatabaseDir() . 'credentials.db' );
    }
}