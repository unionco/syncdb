<?php

namespace unionco\syncdb;
use unionco\syncdb\Service\Logger;
use League\Container\Container;
use unionco\syncdb\Service\DatabaseSync;

class SyncDb
{
    /** @var Container */
    public static $container;

    public function __construct($logPath = null)
    {
        static::$container = new Container();
        static::$container->add('log', Logger::class);
        static::$container->add('dbSync', DatabaseSync::class)->addArgument('log');

        if ($logPath) {
            static::$container->get('log')->pushHandler($logPath);
        }
    }

    public function run(array $config, string $environment)
    {
        $dbSync = static::$container->get('dbSync');
        return $dbSync->syncDatabase($config, $environment);
    }

    public function dumpConfig(array $config, string $environment = 'production')
    {
        $dbSync = static::$container->get('dbSync');
        return $dbSync->dumpConfig($config, $environment);
    }
}
