<?php

namespace App\Model;

use Symfony\Component\Validator\Constraints as Assert;

class SshInfo
{
    /** @var string|null */
    protected $user;
    /** @var string|null */
    protected $host;
    /** @var string|null */
    protected $port;
    /** @var string|null */
    protected $identity;

    /** @var string[] */
    protected $errors = [];

    /** @var string[] */
    protected $warnings = [];

    public function getCommandPrefix(): string
    {
        if (!$this->valid()) {
            $output = "Configuration is invalid: " . print_r([
                'warnings' => $this->getWarnings(),
                'errors' => $this->getErrors(),
            ], true);
            throw new \Exception($output);
        }

        $u = $this->getUser();
        $h = $this->getHost();
        $p = $this->getPort();
        $i = $this->getIdentity();

        $cmd = 'ssh ';
        if ($u) {
            $cmd .= "{$u}@{$h}";
        }
        if ($p) {
            $cmd .= ":{$p}";
        }
        if ($i) {
            $cmd .= " -i {$i}";
        }
        $cmd .= " -- ";
        return $cmd;
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
            $this->port = 22;
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

    /**
     * Get the value of errors
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Get the value of warnings
     */
    public function getWarnings()
    {
        return $this->warnings;
    }
}
