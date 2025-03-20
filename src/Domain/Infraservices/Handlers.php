<?php

namespace RubenCiveiraiglesia\DockerDashboard\Domain\Infraservices;

class Handlers {
    public function all(): array
    {
        return [ new Postgresql() ];
    }
}