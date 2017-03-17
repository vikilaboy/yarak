<?php

namespace Yarak\DB\Seeders;

use Yarak\Config\Config;
use Yarak\Exceptions\FileNotFound;

class SeedRunner
{
    /**
     * Run the given seeder class.
     *
     * @param string $class
     */
    public function run($class)
    {
        $config = Config::getInstance();

        $this->loadSeeders($config);

        $seederClass = $this->resolveSeeder($config, $class);

        $seederClass->run();
    }

    /**
     * Load all the seeders in the seeders directory.
     * 
     * @param Config $config
     */
    protected function loadSeeders(Config $config)
    {
        $seedPath = $config->getSeedDirectory();

        $files = scandir($seedPath);

        $files = array_filter($files, function ($file) {
            return strpos($file, '.php') !== false;
        });

        foreach ($files as $file) {
            require_once $seedPath.$file;
        }
    }

    /**
     * Resolve the given seeder class.
     *
     * @param Config $config
     * @param string $class
     *
     * @return Seeder
     */
    protected function resolveSeeder(Config $config, $class)
    {
        if (!class_exists($class)) {
            $path = $config->getSeedDirectory();

            throw FileNotFound::seederNotFound($class, $path);
        }

        return new $class();
    }
}
