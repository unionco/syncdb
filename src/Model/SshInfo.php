<?php

namespace unionco\syncdb\Model;

class SshInfo extends ValidationModel
{
    /**
     * @var string|null
     **/
    protected $user = '';

    /**
     * @var string|null
     **/
    protected $host = '';

    /**
     * @var string|null
     **/
    protected $port = '';

    /**
     * @var string|null
     **/
    protected $identity = '';

    /**
     * @var string|null $manualPrefix Let the user specify a string, e.g. `ssh user@host -p 2222 -i ~/.ssh/my_key`
     */
    protected $manualPrefix = '';

    public static function fromSshString(string $command)
    {
        $model = new SshInfo();
        $model->manualPrefix = $command;
        return $model;
    }

    public static function fromConfig(array $opts)
    {
        if ($manualPrefix = $opts['manualPrefix'] ?? false) {
            return static::fromSshString($manualPrefix);
        }
        $model = new SshInfo();
        $model->setHost($opts['host'] ?? '');
        $model->setUser($opts['user'] ?? '');
        $model->setPort($opts['port'] ?? '');
        $model->setIdentity($opts['identity'] ?? '');

        if (!$model->valid()) {
            throw new \Exception($model->getErrorsString());
        }

        return $model;
    }

    public function getCommandPrefix(): string
    {
        if ($this->manualPrefix) {
            return $this->manualPrefix;
        }

        $u = $this->getUser();
        $h = $this->getHost();
        $p = $this->getPort();
        $i = $this->getIdentity();

        $cmd = 'ssh ';
        if ($u) {
            $cmd .= "{$u}@{$h} ";
        }
        if ($p) {
            $cmd .= "-p {$p}";
        }
        if ($i) {
            $cmd .= " -i {$i}";
        }
        // $cmd .= " -- ";
        return $cmd;
    }

    public function getScpCommand($remote, $local)
    {
        $u = $this->getUser();
        $h = $this->getHost();
        $p = $this->getPort();
        $i = $this->getIdentity();

        $cmd = 'scp ';
        if ($p) {
            $cmd .= "-P {$p} ";
        }
        if ($i) {
            $cmd .= "-i {$i}";
        }
        if ($u) {
            $cmd .= "{$u}@{$h}:{$remote}";
        } else {
            $cmd .= "{$h}:{$remote}";
        }
        return $cmd . " $local";
    }

    public function valid(): bool
    {
        $errors = [];
        $warnings = [];
        // var_dump($this->manualPrefix); die;
        if ($this->manualPrefix) {
            $this->warnings[] = 'Using manual command prefix - other attributes are ignored';
            return true;
        }

        if (!$this->host) {
            $errors[] = 'Host cannot be empty!';
        }
        if (!$this->user) {
            $warnings[] = 'User is empty. Assuming user is defined in an SSH Config file.';
        }
        if (!$this->port) {
            $warnings[] = 'Port is empty. Assuming default port (22) or defined in an SSH Config file.';
            $this->port = '22';
        }
        if (!$this->identity) {
            $warnings[] = 'Identity file is empty. Assuming the host is defined in an SSH Config file.';
        }

        $this->warnings = $warnings;
        if (count($errors)) {
            $this->errors = $errors;
            return false;
        }

        return true;
    }

    /**
     * Get the value of user
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set the value of user
     *
     * @return  self
     */
    public function setUser($user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get the value of host
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * Set the value of host
     *
     * @return  self
     */
    public function setHost($host)
    {
        $this->host = $host;

        return $this;
    }

    /**
     * Get the value of port
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * Set the value of port
     *
     * @return  self
     */
    public function setPort($port)
    {
        $this->port = $port;

        return $this;
    }

    /**
     * Get the value of identity
     */
    public function getIdentity()
    {
        return $this->identity;
    }

    /**
     * Set the value of identity
     *
     * @return  self
     */
    public function setIdentity($identity)
    {
        $this->identity = $identity;

        return $this;
    }
}
