<?php

namespace unionco\syncdb;
use unionco\syncdb\Service\Logger;
use League\Container\Container;
use unionco\syncdb\Service\DatabaseSync;

class SyncDb
{
    /** @var Container */
    public static $container;

    /**
     * @param string $logPath
     */
    public function __construct(string $logPath = null, int $logLevel = 100)
    {
        static::$container = new Container();
        static::$container->add('log', Logger::class)
            ->addArgument($logPath)
            ->addArgument($logLevel);
        static::$container->add('dbSync', DatabaseSync::class)->addArgument('log');
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

    public function preview(array $config, string $environment)
    {
        $dbSync = static::$container->get('dbSync');
        return $dbSync->preview($config, $environment);
    }
}
