<?php

namespace unionco\syncdb\Service;

use Monolog\Logger as ML;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

class Logger
{
    /** @var null|ML */
    private $ml;

    public const LINE_FORMAT = "[%datetime%] %level_name%\n%message%\n";

    public function __construct(string $logPath = null, int $logLevel = 100)
    {
        if (class_exists('Monolog\Logger')) {
            $this->ml = new ML('default');
            $stdoutHandler = new StreamHandler('php://stdout', $logLevel);
            $stdoutHandler->setFormatter(new LineFormatter(self::LINE_FORMAT, "Y-m-d H:i:s", true, true));

            $this->ml->pushHandler($stdoutHandler);
            if ($logPath) {
                $fileHandler = new StreamHandler($logPath, $logLevel);
                $fileHandler->setFormatter(new LineFormatter(self::LINE_FORMAT, "Y-m-d H:i:s", true, true));
                $this->ml->pushHandler($fileHandler);
            }
        }
    }

    /**
     * @param string $name
     * @param mixed[] $args
     */
    public function __call($name, $args)
    {
        if ($this->ml) {
            try {
                $this->ml->{$name}($args[0]);
            } catch (\Throwable $e) {
                $this->ml->error($e->getMessage());
            }
        }
    }
}
