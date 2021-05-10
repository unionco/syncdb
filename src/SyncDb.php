<?php

namespace unionco\syncdb;
use unionco\syncdb\Service\Logger;
use League\Container\Container;
use unionco\syncdb\Service\DatabaseSync;

class SyncDb
{
    /** @var Container */
    public static $container;

    public function __construct()
    {
        static::$container = new Container();
        static::$container->add('log', Logger::class);
        static::$container->add('dbSync', DatabaseSync::class)->addArgument('log');
    }

    public function run(string $configPath, string $environment)
    {
        $dbSync = static::$container->get('dbSync');
        return $dbSync->run($configPath, $environment);
    }
}
