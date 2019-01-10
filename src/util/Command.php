<?php

namespace unionco\syncdb\util;

class Command
{
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

    public function getTiming()
    {
        return $this->timing;
    }

    public function getLogging()
    {
        return $this->logging;
    }
}
