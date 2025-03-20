<?php

namespace RubenCiveiraiglesia\DockerDashboard\Domain\Command;

class Runner 
{
    protected function runCommand(string $label, string $command): void
    {
        echo "<p>Ejecutando: $label\n";
        $output = [];
        $returnVar = null;
        echo "<p>" . $command . "</p>";
        exec($command . ' 2>&1', $output, $returnVar);
        if ($returnVar !== 0) {
            var_dump( value: $returnVar );
            echo "Error ejecutando comando:\n" . implode("\n", $output) . "\n";
            throw new \RuntimeException("Error ejecutando: $command");
        }
        echo "Resultado:\n<pre>" . implode("\n", $output) . "</pre>\n";
    }
}