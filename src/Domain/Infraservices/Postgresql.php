<?php

namespace RubenCiveiraiglesia\DockerDashboard\Domain\Infraservices;

use RubenCiveiraiglesia\DockerDashboard\Model\Service;

class Postgresql implements ServiceHandler
{
    public function handleService(Service $service): bool
    {
        return $service->kind == 'postgres';
    }

    public function bindCredential(Service $service, string $role, string $user, string $password, string $path, array $params)
    {
        $containerName = $service->name;// 'postgresql'; // Nombre del contenedor en Docker
        switch ($role) {
            case 'drop':
                if ($user) {
                    $this->dropUser($containerName, $user);
                }
                if ($path) {
                    $this->dropDatabase($containerName, $path);
                }
                break;
            case 'unbind':
                if (!$user) {
                    throw new \Exception('User required to bind');
                }
                if (!$path) {
                    throw new \Exception('User required to bind');
                }
                $this->revokeUserPermissions($containerName, $path, $user);
                break;
            default:
                if (!$user) {
                    throw new \Exception('User required to bind');
                }
                if (!$path) {
                    throw new \Exception('User required to bind');
                }
                $this->appendCredential($containerName, $role, $user, $password, $path, $params);
                break;
        }
    }
    private function appendCredential(string $containerName, string $role, string $user, string $password, string $path, array $params)
    {
        $schema = $params['schema'] ?? 'public';
        // 1️⃣ Crear la base de datos si no existe
        $this->execInContainer($containerName, "
        psql -U root -d postgres -tc \"SELECT 1 FROM pg_database WHERE datname = '$path'\" | grep -q 1 || psql -U root -d postgres -c \"CREATE DATABASE \\\"$path\\\";\"
        ");

        // 2️⃣ Crear el esquema si no existe
        $this->execInContainer($containerName, "
        psql -U root -d postgres -d $path -tc \"SELECT 1 FROM information_schema.schemata WHERE schema_name = '$schema'\" | grep -q 1 || psql -U root -d postgres -d '$path' -c \"CREATE SCHEMA \\\"$schema\\\";\"
        ");

        // 3️⃣ Crear el usuario si no existe
        if ($password) {
            $this->execInContainer($containerName, "
            psql -U root -d postgres -tc \"SELECT 1 FROM pg_roles WHERE rolname = '$user'\" | grep -q 1 || psql -U root -d postgres -c \"CREATE USER \\\"$user\\\" WITH PASSWORD '$password';\"
            ");
        }

        // 4️⃣ Asignar permisos según el rol
        switch ($role) {
            case 'owner':
                $this->execInContainer($containerName, "psql -U root -d postgres -d $path -c \"ALTER DATABASE \\\"$path\\\" OWNER TO \\\"$user\\\";\"");
                $this->execInContainer($containerName, "psql -U root -d postgres -d $path -c \"GRANT ALL PRIVILEGES ON SCHEMA \\\"$schema\\\" TO \\\"$user\\\";\"");
                $this->execInContainer($containerName, "psql -U root -d postgres -d $path -c \"GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA \\\"$schema\\\" TO \\\"$user\\\";\"");
                $this->execInContainer($containerName, "psql -U root -d postgres -d $path -c \"ALTER DEFAULT PRIVILEGES IN SCHEMA \\\"$schema\\\" GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO \\\"$user\\\";\"");
                break;
            case 'write':
                $this->execInContainer($containerName, "psql -U root -d postgres -d $path -c \"GRANT CONNECT ON DATABASE \\\"$path\\\" TO \\\"$user\\\";\"");
                $this->execInContainer($containerName, "psql -U root -d postgres -d $path -c \"GRANT USAGE ON SCHEMA \\\"$schema\\\" TO \\\"$user\\\";\"");
                $this->execInContainer($containerName, "psql -U root -d postgres -d $path -c \"GRANT INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA \\\"$schema\\\" TO \\\"$user\\\";\"");
                $this->execInContainer($containerName, "psql -U root -d postgres -d $path -c \"ALTER DEFAULT PRIVILEGES IN SCHEMA \\\"$schema\\\" GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES TO \\\"$user\\\";\"");
                break;
            case 'read':
                $this->execInContainer($containerName, "psql -U root -d postgres -d $path -c \"GRANT CONNECT ON DATABASE \\\"$path\\\" TO \\\"$user\\\";\"");
                $this->execInContainer($containerName, "psql -U root -d postgres -d $path -c \"GRANT USAGE ON SCHEMA \\\"$schema\\\" TO \\\"$user\\\";\"");
                $this->execInContainer($containerName, "psql -U root -d postgres -d $path -c \"GRANT SELECT ON ALL TABLES IN SCHEMA \\\"$schema\\\" TO \\\"$user\\\";\"");
                $this->execInContainer($containerName, "psql -U root -d postgres -d $path -c \"ALTER DEFAULT PRIVILEGES IN SCHEMA \\\"$schema\\\" GRANT SELECT ON ALL TABLES TO \\\"$user\\\";\"");
                break;
        }
        echo "✅ Usuario `$user` con rol `$role` configurado en DB `$path` y esquema `$schema`.\n";
    }

    private function dropDatabase(string $containerName, string $dbName)
    {
        // 1️⃣ Revocar conexiones activas a la base de datos antes de eliminarla
        $this->execInContainer($containerName, "
    psql -U root -d postgres -c \"UPDATE pg_database SET datallowconn = 'false' WHERE datname = '$dbName';\"
    psql -U root -d postgres -c \"SELECT pg_terminate_backend(pg_stat_activity.pid) FROM pg_stat_activity WHERE pg_stat_activity.datname = '$dbName';\"
");

        // 2️⃣ Eliminar la base de datos si existe
        $this->execInContainer($containerName, "
    psql -U root -d postgres -tc \"SELECT 1 FROM pg_database WHERE datname = '$dbName'\" | grep -q 1 &&
    psql -U root -d postgres -c \"DROP DATABASE \\\"$dbName\\\";\"
");
    }

    private function dropUser(string $containerName, string $username)
    {
        // 3️⃣ Eliminar el usuario si existe
        $this->execInContainer($containerName, "
    psql -U root -d postgres -tc \"SELECT 1 FROM pg_roles WHERE rolname = '$username'\" | grep -q 1 &&
    psql -U root -d postgres -c \"DROP USER \\\"$username\\\";\"
");
    }

    private function revokeUserPermissions(string $containerName, string $dbName, string $username): void {
        // Reasignar objetos y revocar permisos
        $this->execInContainer($containerName, "
            psql -U root -d $dbName -c \"REASSIGN OWNED BY \\\"$username\\\" TO postgres;\"
            psql -U root -d $dbName -c \"DROP OWNED BY \\\"$username\\\";\"
        ");
    
        echo "✅ Todos los permisos de `$username` en la base de datos `$dbName` han sido revocados.\n";
    }

    private function execInContainer(string $container, string $command)
    {
        $command = escapeshellarg($command);
        shell_exec("docker exec -i $container bash -c $command");
    }
}


