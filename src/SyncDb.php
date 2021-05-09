<?php

namespace unionco\syncdb;
use unionco\syncdb\Service\Logger;
use League\Container\Container;
use unionco\syncdb\Service\DatabaseSync;

class SyncDb
{
    // public $service;
    public static $container;

    public function __construct()
    {
        // $this->init();
        static::$container = new Container();
        static::$container->add(Logger::class);
        static::$container->add(DatabaseSync::class)->addArgument(Logger::class);
    }

    // protected function init(): void
    // {
    //     $this->service = new DatabaseSync();
    // }
}
