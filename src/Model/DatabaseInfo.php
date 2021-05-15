<?php

namespace unionco\syncdb\Model;

use Exception;
use unionco\syncdb\SyncDb;
use unionco\syncdb\Model\SshInfo;
use unionco\syncdb\Service\Config;
use unionco\syncdb\Model\SetupStep;
use unionco\syncdb\Model\TableView;
use unionco\syncdb\Service\DatabaseSync;
use unionco\syncdb\Model\DockerDatabaseInfo;

class DatabaseInfo extends ValidationModel implements TableView
{
    public const MYSQL = 'mysql';
    public const POSTGRES = 'pgsql';

    public const OVERRIDE = 'dbOverride';
    public const DRIVER = 'driver';
    public const PORT = 'port';
    public const HOST = 'host';
    public const USER = 'user';
    public const NAME = 'name';
    public const PASS = 'pass';
    public const ARGS = 'args';
    public const IGNORE_TABLES = 'ignoreTables';

    // public static $readFromDotEnv = false;

    /** @var string */
    protected $driver = 'mysql';

    /** @var string */
    protected $user = '';

    /** @var string */
    protected $pass = '';

    /** @var string */
    protected $host = '';

    /** @var string|int */
    protected $port = '';

    /** @var string */
    protected $name = '';

    /** @var string[] */
    protected $ignoreTables = [];

    protected static $overrides = [];

    protected $args;

    /**
     * Parse an array of configuration options into a DatabaseInfo (or child class) object
     * This method explicitly parses commands for a remote environment
     */
    public static function remoteFromConfig(array $opts, SshInfo $ssh): self
    {
        // $overrides = [];
        // if (\key_exists(self::OVERRIDE, $opts)) {
        //     $overrides = $opts[self::OVERRIDE];
        // }
        // static::setOverrides($overrides);
        $model = self::remoteFromSsh($opts, $ssh);
        $model = self::configureOverrides($model);

        return $model;
    }

    /**
     * Run commands on the remote server (based on the configuration options)
     * to determine the remote database connection
     */
    public static function remoteFromSsh(array $config, SshInfo $ssh): self
    {
        $remoteWorkingDir = $config['remoteWorkingDir'];
        $cmd = new SetupStep('Get Remote DB Config', ["grep {$remoteWorkingDir}/.env -e \"DB_\""], true, false, true);

        /** @var DatabaseSync */
        $service = SyncDb::$container->get('dbSync');

        $result = $service->runRemote($ssh, $cmd);
        if (!$result) {
            throw new \Exception('Command failed');
        }


        // Consider if the remote configuration uses docker
        // (and may need alternate hosts/ports to be reached outside
        // of the container)
        $dockerOverride = false;
        if ($config[Config::C_DOCKER] === true) {
            [
                'host' => $host,
                'port' => $port,
            ] = $config[Config::C_DOCKER_DB];
            $dockerOverride = true;
            $model = DockerDatabaseInfo::fromGrepOutput($result);
            $model->setHost($host);
            $model->setPort($port);
            return $model;
        }
        $model = self::fromGrepOutput($result);
        return $model;
    }

    public static function localFromConfig(array $config): self
    {
        $localWorkingDir = $config['localWorkingDir'];
        $cmd = new SetupStep('Get Local DB Config', ["grep {$localWorkingDir}/.env -e \"DB_\""], false, false, true);
        $service = SyncDb::$container->get('dbSync');
        $result = $service->runLocal($cmd);
        $model = self::fromGrepOutput($result);

        return $model;
    }

    public static function fromGrepOutput(string $output): self
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
            'host' => '/^DB_SERVER=(.*)$/m',
        ];
        $model = new self();

        foreach ($rules as $handle => $rule) {
            $matches = [];
            \preg_match_all($rule, $output, $matches, PREG_SET_ORDER, 0);
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

    public function getTempFile(bool $absolute = true, bool $remote = true): string
    {
        $relative = '';
        switch ($this->driver) {
            case 'mysql':
                $relative = 'db.sql';
                break;
            case 'pgsql':
                $relative = 'db.dump';
                break;
            default:
                throw new \Exception('Invalid driver');
        }
        return $absolute ? ($this->getTempDir($remote) . '/' . $relative) : $relative;
    }

    public function getTempDir(bool $remote = true): string
    {
        return '/tmp';
    }

    public function getArchiveFile(bool $absolute = true, bool $remote = true): string
    {
        $relative = $this->getTempFile($absolute, $remote) . '.bz2';
        return $absolute ? ($this->getTempDir($remote) . '/' . $relative) : $relative;
    }

    public function getDriver(): string
    {
        return \strtolower($this->driver);
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
        return $this->ignoreTables ?? [];
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

    public function getRows(): array
    {
        $keys = ['driver', 'user', 'pass', 'name', 'port', 'ignoreTables'];
        $rows = [];
        foreach ($keys as $key) {
            $getter = "get" . \ucFirst($key);
            $value = $this->{$getter}() ?? '(empty)';
            $rows[] = [$key, $value];
        }
        return $rows;
    }

    /**
     * Get the value of overrides
     */
    public static function getOverrides(): array
    {
        return static::$overrides;
    }

    /**
     * Set the value of overrides
     *
     * @return void
     */
    public static function setOverrides(array $overrides): void
    {
        static::$overrides = $overrides;
        // return $this;
    }
}
