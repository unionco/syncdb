<?php

namespace unionco\syncdb\Model;

use unionco\syncdb\Service\DatabaseSync;

abstract class Step
{
    /** @var int */
    public static $nextId = 1;

    /** @var int */
    protected $id;

    /** @var string */
    protected $name = '';

    /** @var bool */
    protected $remote = true;

    /** @var string[] */
    protected $commands = [];

    /** @var bool */
    protected $chain = true;

    /** @var bool */
    protected $ignoreWarnings = false;

    /** @var null|int */
    protected $relatedId;

    public function __construct()
    {
        $this->setId();
    }
    // public function __construct(string $name, array $commands, bool $remote = true, bool $chain = true, bool $ignoreWarnings = false)
    // {
    //     $this->setName($name);
    //     $this->setId();
    //     $this->remote = $remote;
    //     $this->setCommands($commands);
    //     $this->chain = $chain;
    //     $this->ignoreWarnings = $ignoreWarnings;
    // }

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
     * @param bool $stripNewslines Replace newlines with a literal '\n'
     * @return string
     */
    public function getCommandString(SshInfo $ssh = null, bool $scramble = false, bool $stripNewlines = false): string
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

        if ($stripNewlines) {
            $cmd = preg_replace("\n", "\\n", $cmd);
        }
        return $cmd;
    }

    /**
     * Set the id for this step and increment the static count
     */
    public function setId(mixed $id = null): void
    {
        if (!$id) {
            $this->id = static::$nextId;
            static::$nextId++;
        } else {
            $this->id = $id;
        }
    }

    public function getId(): int
    {
        return $this->id;
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

    /**
     * @return null|int
     */
    public function getRelatedId()
    {
        return $this->relatedId;
    }

    public function setRelatedId(int $id)
    {
        $this->relatedId = $id;
        return $this;
    }

    public function setRelated(Step $step)
    {
        $this->relatedId = $step->getId();
        return $this;
    }

    public function getRemote(): bool
    {
        return $this->remote;
    }

    public function setRemote(bool $remote): self
    {
        $this->remote = $remote;
        return $this;
    }
}
