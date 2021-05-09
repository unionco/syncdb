<?php

namespace unionco\syncdb\Model;

use unionco\syncdb\SyncDb;
use unionco\syncdb\Model\SshInfo;
use unionco\syncdb\Model\SetupStep;
use unionco\syncdb\Service\DatabaseSync;

class DatabaseInfo extends ValidationModel
{
    public static $readFromDotEnv = false;

    /** @var string */
    protected $driver = 'mysql';

    /** @var string */
    protected $user;

    /** @var string */
    protected $pass;

    /** @var string */
    protected $host;

    /** @var string|int */
    protected $port;

    /** @var string */
    protected $name;

    /** @var string[] */
    protected $ignoreTables;

    protected $args;

    public static function remoteFromConfig(array $opts, ?SshInfo $ssh = null)
    {
        self::$readFromDotEnv = $opts['readDbConfigFromDotEnv'];

        if (!self::$readFromDotEnv) {
            $model = new DatabaseInfo();
            $model->setDriver($opts['driver']);
            $model->setUser($opts['dbUser']);
            $model->setPass($opts['dbPass']);
            $model->setHost($opts['dbHost']);
            $model->setName($opts['dbName']);
            $model->setPort($opts['dbPort']);
            $model->setArgs($opts['dbArgs']);
        } else {
            $model = self::remoteFromSsh($opts, $ssh);
        }
        return $model;
    }

    public static function remoteFromSsh(array $config, SshInfo $ssh)
    {
        $remoteWorkingDir = $config['remoteWorkingDir'];
        $cmd = new SetupStep('Get Remote DB Config', ["cd {$remoteWorkingDir}; grep .env -e \"DB_\""]);

        /** @var DatabaseSync */
        $service = SyncDb::$container->get('dbSync');
        $result = $service->runRemote($ssh, $cmd);
        $model = self::fromGrepOutput($result);
        return $model;
        // var_dump($model); die;
    }

    public static function localFromConfig(array $config)
    {
        $localWorkingDir = $config['localWorkingDir'];
        $cmd = new SetupStep('Get Local DB Config', ["cd {$localWorkingDir}; grep .env -e \"DB_\""]);
        $service = SyncDb::$container->get('dbSync');
        $result = $service->runLocal($cmd);
        $model = self::fromGrepOutput($result);
        return $model;
    }

    private static function fromGrepOutput($output)
    {
        // If successful, the result will look like:
        // DB_DRIVER=xxxx
        // DB_USER="...."

        $rules = [
            'driver' => '/^DB_DRIVER=(.*)$/m',
            'user' => '/^DB_USER=(.*)$/m',
            'pass' => '/^DB_PASSWORD=(.*)$/m',
            'name' => '/^DB_DATABASE=(.*)$/m',
            'port' => '/^DB_PORT=(.*)$/m',
            'host' => '/^DB_SERVER=(.*)$/m'
        ];
        $model = new DatabaseInfo();
        foreach ($rules as $handle => $rule) {
            $matches = [];
            \preg_match_all($rule, $output, $matches, PREG_SET_ORDER, 0);
            // var_dump($matches);
            if ($matches) {
                $model->{$handle} = $matches[0][1];
            }
        }
        if (!$model->valid()) {
             throw new \Exception('DB config is invalid');
        }
        return $model;
    }

    public function valid(): bool
    {
        if (!$this->user) {
            $this->errors[] = 'User is not defined';
        }
        if (!$this->pass) {
            $this->errors[] = 'Password is not defined';
        }
        if (!$this->name) {
            $this->errors[] = 'Name is not defined';
        }
        if (!$this->port) {
            $this->warnings[] = 'Port is not defined, using defaults';
        }
        return empty($this->errors);
    }

    public function getTempFile(bool $absolute = true, bool $remote = true)
    {
        $relative = 'db.sql';
        return $absolute ? ($this->getTempDir($remote) . '/' . $relative) : $relative;
    }

    public function getTempDir(bool $remote = true)
    {
        return '/tmp';
    }

    public function getArchiveFile(bool $absolute = true, bool $remote = true)
    {
        $relative = 'db.sql.bz2';
        return $absolute ? ($this->getTempDir($remote) . '/' . $relative) : $relative;
    }

    public function getDriver(): string
    {
        return $this->driver;
    }

    public function setDriver(string $driver): self
    {
        $this->driver = $driver;
        return $this;
    }

    /**
     * Get the value of user
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
    public function setUser(string $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get the value of pass
     */
    public function getPass(): string
    {
        return $this->pass;
    }

    /**
     * Set the value of pass
     *
     * @return  self
     */
    public function setPass(string $pass): self
    {
        $this->pass = $pass;

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
    public function setHost(string $host): self
    {
        $this->host = $host;

        return $this;
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
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get the value of ignoreTables
     */
    public function getIgnoreTables(): array
    {
        return $this->ignoreTables;
    }

    /**
     * Set the value of ignoreTables
     *
     * @return  self
     */
    public function setIgnoreTables(array $ignoreTables): self
    {
        $this->ignoreTables = $ignoreTables;

        return $this;
    }

    /**
     * Get the value of args
     */
    public function getArgs(): array
    {
        return $this->args;
    }

    /**
     * Set the value of args
     *
     * @return  self
     */
    public function setArgs(string $args): self
    {
        $this->args = $args;

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
}
