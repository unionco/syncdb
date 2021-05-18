<?php

namespace unionco\syncdb\Model;

use unionco\syncdb\Model\TableView;

class SshInfo extends ValidationModel implements TableView
{
    /**
     * @var string
     **/
    protected $user = '';

    /**
     * @var string
     **/
    protected $host = '';

    /**
     * @var int|null
     **/
    protected $port = 22;

    /**
     * @var string
     **/
    protected $identity = '';

    public static function fromSshString(string $command): self
    {
        $model = new SshInfo();
        return $model;
    }

    public static function fromConfig(array $opts): self
    {
        $model = new SshInfo();
        $model->setHost($opts['host'] ?? '');
        $model->setUser($opts['user'] ?? '');
        $model->setPort($opts['port'] ?? 22);
        $model->setIdentity($opts['identity'] ?? '');

        if (!$model->valid()) {
            throw new \Exception($model->getErrorsString());
        }

        return $model;
    }

    public function getCommandPrefix(): string
    {
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
        $cmd .= ' -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -o LogLevel=ERROR ';
        return $cmd;
    }

    /**
     * Generate the scp command based on the remote and local files
     */
    public function getScpCommand(string $remote, string $local): string
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
            $cmd .= "-i {$i} ";
        }

        $cmd .= ' -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -o LogLevel=ERROR ';

        if ($u) {
            $cmd .= "{$u}@{$h}:{$remote} ";
        } else {
            $cmd .= "{$h}:{$remote} ";
        }

        return $cmd . " $local";
    }

    public function valid(): bool
    {
        $errors = [];
        $warnings = [];

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
     * @return string
     */
    public function getUser(): string
    {
        return $this->user;
    }

    /**
     * Set the value of user
     *
     * @return  self
     */
    public function setUser(string $user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get the value of host
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * Set the value of host
     *
     * @return  self
     */
    public function setHost(string $host)
    {
        $this->host = $host;

        return $this;
    }

    /**
     * Get the value of port
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * Set the value of port
     *
     * @return  self
     */
    public function setPort(int $port)
    {
        $this->port = $port;

        return $this;
    }

    /**
     * Get the value of identity
     */
    public function getIdentity(): string
    {
        return $this->identity;
    }

    /**
     * Set the value of identity
     *
     * @return  self
     */
    public function setIdentity(string $identity)
    {
        $this->identity = $identity;

        return $this;
    }

    /** Used for table views */
    public function getRows(): array
    {
        $keys = ['host', 'user', 'port', 'identity', 'commandPrefix', 'warnings', 'errors'];
        $rows = [];
        foreach ($keys as $key) {
            $getter = "get" . \ucFirst($key);
            $value = $this->{$getter}();
            $value = preg_replace("\n", '\n', $value);
            $rows[] = [$key, $value];
        }
        return $rows;
    }
}
