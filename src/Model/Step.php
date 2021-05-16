<?php

namespace unionco\syncdb\Model;

use unionco\syncdb\Service\DatabaseSync;

abstract class Step
{
    public static $nextId = 1;

    public $id;

    public $name;

    public $remote = true;

    public $commands;

    public $chain = true;

    public $ignoreWarnings = false;

    public function __construct($name, $commands, $remote = true, $chain = true, $ignoreWarnings = false)
    {
        $this->setName($name);
        $this->setId();
        $this->remote = $remote;
        $this->setCommands($commands);
        $this->chain = $chain;
        $this->ignoreWarnings = $ignoreWarnings;
    }

/**
 * Get the value of name
 */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set the value of name
     *
     * @return  self
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    public function setCommands($commands)
    {
        if (\is_array($commands)) {
            $this->commands = $commands;
        } else {
            $this->commands = [$commands];
        }
        return $this;
    }

    public function getCommands()
    {
        return $this->commands;
    }

    public function getCommandString(SshInfo $ssh = null, bool $scramble = false): string
    {
        $cmd = join($this->chain ? ' && ' : '; ', $this->getCommands());
        // escape quotes
        $cmd = str_replace("'", "\\\'", $cmd);
        $cmd = str_replace('"', '\\\"', $cmd);

        if ($this->remote && $ssh) {
            $cmd = "{$ssh->getCommandPrefix()} \\\"$cmd\\\"";
        }
        if ($this->ignoreWarnings) {
            $cmd .= " 2>/dev/null";
        }
        if ($scramble) {
            return DatabaseSync::scramble($cmd);
        }
        return $cmd;

    }

    protected function setId(): void
    {
        $this->id = static::$nextId;
        static::$nextId++;
    }

    /**
     * Get the value of ignoreWarnings
     */
    public function getIgnoreWarnings()
    {
        return $this->ignoreWarnings;
    }

    /**
     * Set the value of ignoreWarnings
     *
     * @return  self
     */
    public function setIgnoreWarnings($ignoreWarnings)
    {
        $this->ignoreWarnings = $ignoreWarnings;

        return $this;
    }
}
