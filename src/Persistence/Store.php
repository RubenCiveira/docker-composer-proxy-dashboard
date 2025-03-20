<?php

namespace RubenCiveiraiglesia\DockerDashboard\Persistence;

class Store
{
    private string $filePath;
    private array $data;

    private array $updated = [];

    private array $deleted = [];

    public function __construct(string $filepath)
    {
        $this->filePath = $filepath;
        $this->load();
    }

    private function load(): void
    {
        if (file_exists($this->filePath)) {
            $json = file_get_contents($this->filePath);
            $this->data = json_decode($json, true) ?? [];
        } else {
            $this->data = [];
        }
    }

    private function save(): void
    {
        $handle = fopen($this->filePath, 'c+'); // 'c+' evita truncar el archivo
        if (flock($handle, LOCK_EX)) { // Bloqueo exclusivo del archivo
            // Leer el archivo nuevamente para mantener coherencia
            fseek($handle, 0);
            $json = fread($handle, filesize($this->filePath) ?: 1);
            $existingData = json_decode($json, true) ?? [];

            // Aplicar eliminaciones
            foreach ($this->deleted as $key) {
                unset($existingData[$key]);
            }

            // Aplicar modificaciones y nuevos valores
            foreach ($this->updated as $key => $value) {
                $existingData[$key] = $value;
            }

            // Limpiar los buffers de cambios
            $this->data = json_decode(json_encode($existingData), true);
            $this->updated = [];
            $this->deleted = [];

            // Guardar los cambios en el archivo
            ftruncate($handle, 0); // Borra el contenido del archivo antes de escribir
            rewind($handle);
            fwrite($handle, json_encode($existingData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            fflush($handle); // Asegurar que los datos se escriben en disco
            flock($handle, LOCK_UN); // Liberar bloqueo
        } else {
            throw new \Exception('Unable to lock the file');
        }
        fclose($handle);
    }

    public function set(string $key, mixed $value): void
    {
        $this->updated[$key] = $value;
        $this->save();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function delete(string $key): void
    {
        if (isset($this->data[$key])) {
            $this->deleted[] = $key;
            $this->save();
        }
    }

    public function all(): array
    {
        return $this->data;
    }

    public function clear(): void
    {
        $this->data = [];
        $this->save();
    }

}
