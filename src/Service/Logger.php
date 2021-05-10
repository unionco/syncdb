<?php

namespace unionco\syncdb\Service;

use Monolog\Logger as ML;
use Monolog\Handler\StreamHandler;

class Logger
{
    public function __construct()
    {
        if (class_exists('Monolog\Logger')) {
            // $this->logger = new Logger()
            $this->ml = new ML('default');
            $this->ml->pushHandler(new StreamHandler('php://stdout', ML::DEBUG));
        }
    }

    public function __call($name, $args)
    {
        if ($this->ml) {
            if (count($args) > 1 && $args[1]) {
                $context = $args[1];
                $context = \array_map(function ($val) {
                    if (\is_object($val) || \is_array($val)) {
                        return print_r($val, true);
                    }
                    return $val;
                }, $context);
                try {
                    return $this->ml->{$name}($args[0], $context);
                } catch (\Throwable $e) {
                    $this->ml->error($e->getMessage());
                }
            }
            try {
                $this->ml->{$name}($args);
            } catch (\Throwable $e) {
                $this->ml->error($e->getMessage());
            }
        }
    }
}
