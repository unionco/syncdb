<?php

namespace unionco\syncdb\util;

class Command
{
    /** @var string */
    protected $cmd = '';

    /** @var false|string */
    protected $timing = false;

    /** @var false|string */
    protected $logging = false;

    /**
     * @param array $opts
     */
    public function __construct($opts = [])
    {
        $this->cmd = $opts['cmd'] ?? '';
        $this->logging = $opts['logging'] ?? false;
        $this->timing = $opts['timing'] ?? false;
    }

    public function getCommand(): string
    {
        return $this->cmd;
    }

    public function getTiming(): string
    {
        return $this->timing;
    }

    public function getLogging(): string
    {
        return $this->logging;
    }
}
