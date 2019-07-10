<?php

namespace unionco\syncdb\util;

class Command
{
    /** @var string */
    protected $cmd = '';

    /** @var bool */
    protected $timed = false;

    /** @var string */
    protected $name = '';

    /**
     * @param array $opts
     */
    public function __construct($opts = [])
    {
        $this->cmd = $opts['cmd'] ?? '';
        $this->timed = $opts['timed'] ?? false;
        $this->name = $opts['name'] ?? '';
    }

    public function getCommand(): string
    {
        return $this->cmd;
    }

    public function getScrubbedCommand(): string
    {
        $cmd = $this->getCommand();
        // Remove passwords from mysql/mysqldump commands
        $scrubbed = preg_replace('/\-\-password=".*"/', '--password="*****"', $cmd);

        return $scrubbed;
    }

    public function getTimed(): bool
    {
        return $this->timed;
    }

    public function getLogging(): string
    {
        return $this->logging;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
