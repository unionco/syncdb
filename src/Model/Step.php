<?php

namespace unionco\syncdb\Model;

abstract class Step
{
    public static $nextId = 1;

    public $id;

    public $name;

    public $remote = true;

    public $commands;

    public $chain = true;

    public function __construct($name, $commands, $remote = true, $chain = true)
    {
        $this->setName($name);
        $this->setId();
        $this->remote = $remote;
        $this->setCommands($commands);
        $this->chain = $chain;
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

    public function getCommandString($ssh = null)
    {
        $cmd = join($this->chain ? ' && ' : '; ', $this->getCommands());
        if (!$this->remote || !$ssh) {
            return $cmd;
        }
        return "{$ssh->getCommandPrefix()} '$cmd'";
    }

    protected function setId()
    {
        $this->id = static::$nextId;
        static::$nextId++;
    }
}
