<?php

namespace App;

class Component
{
    private string $name;

    private string $basePath;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->basePath = app()->basePath() . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . $this->name;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function defaultConfigPath(): string
    {
        return $this->configPath("$this->name.php");
    }

    public function configPath(string $path = ''): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'config' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    public function databasePath(string $path = ''): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'database' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    public function routesPath(string $path = ''): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'routes' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    public function resourcesPath(string $path = ''): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'resources' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }
}
