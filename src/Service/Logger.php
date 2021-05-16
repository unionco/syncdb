<?php

namespace unionco\syncdb\Service;

use Monolog\Logger as ML;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

class Logger
{
    /** @var null|ML */
    private $ml;

    public const LINE_FORMAT = "[%datetime%] %level_name% - %message%";

    public function __construct()
    {
        if (class_exists('Monolog\Logger')) {
            // $this->logger = new Logger()
            $this->ml = new ML('default');
            $stdoutHandler = new StreamHandler('php://stdout', ML::DEBUG);
            $stdoutHandler->setFormatter(new LineFormatter(self::LINE_FORMAT, "Y-m-d H:i:s", true, true));
            // $fileHandler = new StreamHandler('/tmp/syncdb.log')
            $this->ml->pushHandler($stdoutHandler);
        }
    }

    public function pushHandler(string $filePath): void
    {
        if ($this->ml) {
            $this->ml->pushHandler(new StreamHandler($filePath, ML::DEBUG));
        }
    }

    public function __call($name, $args)
    {
        if ($this->ml) {
            // if (count($args) > 1 && $args[1]) {
            //     $context = $args[1];
            //     $context = \array_map(function ($val) {
            //         if (\is_object($val) || \is_array($val)) {
            //             return print_r($val, true);
            //         }
            //         return $val;
            //     }, $context);
            //     try {
            //         return $this->ml->{$name}($args[0], $context);
            //     } catch (\Throwable $e) {
            //         $this->ml->error($e->getMessage());
            //     }

            // }
            try {
                $this->ml->{$name}($args[0]);
            } catch (\Throwable $e) {
                $this->ml->error($e->getMessage());
            }
        }
    }
}
