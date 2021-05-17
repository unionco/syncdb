<?php

namespace unionco\syncdb\Model;

use unionco\syncdb\Service\DatabaseSync;

abstract class Step
{
    public static $nextId = 1;

    /** @var int */
    public $id;

    /** @var string */
    public $name;

    /** @var bool */
    public $remote = true;

    /** @var string[] */
    public $commands;

    /** @var bool */
    public $chain = true;

    /** @var bool */
    public $ignoreWarnings = false;

    public function __construct(string $name, array $commands, bool $remote = true, bool $chain = true, bool $ignoreWarnings = false)
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
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the value of name
     *
     * @return  self
     */
    public function setName(string $name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @param string[]|string $commands
     * @return self
     */
    public function setCommands($commands)
    {
        if (\is_array($commands)) {
            $this->commands = $commands;
        } else {
            $this->commands = [$commands];
        }
        return $this;
    }

    /**
     * @return string[]
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * Return the formatted command text, based on the context
     * @param SshInfo $ssh
     * @param bool $scramble Should sensitive information be censored
     * @return string
     */
    public function getCommandString(SshInfo $ssh = null, bool $scramble = false): string
    {
        // If the commands should be chained, join them with `&&`, otherwise `;`
        $cmd = join($this->chain ? ' && ' : '; ', $this->getCommands());

        // If the command is to be run remotely and the ssh context is provided
        if ($this->remote && $ssh) {
            if ($this->ignoreWarnings) {
                $cmd .= " 2>/dev/null";
            }

            $cmd = <<<EOFPHP
{$ssh->getCommandPrefix()} /bin/sh << EOF
{$cmd}
EOF
EOFPHP;
        }
        if ($scramble) {
            return DatabaseSync::scramble($cmd);
        }

        return $cmd;
    }

    /**
     * Set the id for this step and increment the static count
     */
    protected function setId(): void
    {
        $this->id = static::$nextId;
        static::$nextId++;
    }

    /**
     * Get the value of ignoreWarnings
     */
    public function getIgnoreWarnings(): bool
    {
        return $this->ignoreWarnings;
    }

    /**
     * Set the value of ignoreWarnings
     *
     * @return  self
     */
    public function setIgnoreWarnings(bool $ignoreWarnings)
    {
        $this->ignoreWarnings = $ignoreWarnings;

        return $this;
    }
}
