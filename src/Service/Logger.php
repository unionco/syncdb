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
            if (\is_array($args) || \is_object($args)) {
                $args = json_encode($args);
            }
            $this->ml->{$name}($args);
        }
    }
}
