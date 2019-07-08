<?php

namespace unionco\syncdb\models;

use Symfony\Component\Console\Output\Output;
use unionco\syncdb\SyncDb;

class Environment
{
    const ENV_DEV = 'dev';
    const ENV_STAGING = 'staging';
    const ENV_PRODUCTION = 'production';

    public $environment;

    /** @var string */
    private $_error = '';

    /** @var string */
    public $name = '';

    /** @var string */
    public $host = '';

    /** @var int */
    public $port = 22;

    /** @var string */
    public $username = '';

    /** @var string */
    public $phpPath = '';

    /** @var string */
    public $root = '';

    /** @var string */
    public $backupDirectory = '';

    /** @var int */
    public $verbosity = Output::VERBOSITY_NORMAL;

    /** @var array<string> */
    public static $required = [
        'name',
        'host',
        'phpPath',
        'root',
    ];

    /**
     * @param array $config
     * @return void
     */
    public function readConfig(array $config)
    {
        if (key_exists('name', $config)) {
            $this->setName($config['name']);
        }

        if (key_exists('host', $config)) {
            $this->setHost($config['host']);
        }

        if (key_exists('port', $config)) {
            $this->setPort($config['port']);
        }

        if (key_exists('username', $config)) {
            $this->setUsername($config['username']);
        }

        if (key_exists('phpPath', $config)) {
            $this->setPhpPath($config['phpPath']);
        }

        if (key_exists('root', $config)) {
            $this->setRoot($config['root']);
        }

        if (key_exists('backupDirectory', $config)) {
            $this->setBackupDirectory($config['backupDirectory']);
        }

        if (key_exists('environment', $config)) {
            $this->setEnvironment($config['environment']);
        }

        if (key_exists('verbosity', $config)) {
            $this->setVerbosity($config['verbosity']);
        }
    }

    /**
     * @param string $name
     * @return void
     */
    public function setName(string $name)
    {
        $this->name = $name;
    }

    /**
     * @param string $host
     * @return void
     */
    public function setHost(string $host)
    {
        $this->host = $host;
    }

    /**
     * @param int $port
     * @return void
     */
    public function setPort(int $port)
    {
        $this->port = $port;
    }

    /**
     * @param string $username
     * @return void
     */
    public function setUsername(string $username)
    {
        $this->username = $username;
    }

    /**
     * @param string $phpPath
     * @return void
     */
    public function setPhpPath(string $phpPath)
    {
        $this->phpPath = $phpPath;
    }

    /**
     * @param string $root
     * @return void
     */
    public function setRoot(string $root)
    {
        $this->root = $root;
    }

    public function setEnvironment(string $environment): void
    {
        $this->environment = $environment;
    }

    /**
     * @param string $backupDirectory
     * @return void
     */
    public function setBackupDirectory(string $backupDirectory)
    {
        if (substr($backupDirectory, strlen($backupDirectory) - 1, 1) != DIRECTORY_SEPARATOR) {
            $backupDirectory .= DIRECTORY_SEPARATOR;
        }
        $this->backupDirectory = $backupDirectory;
    }

    public function setVerbosity(int $verbosity)
    {
        $this->verbosity = $verbosity;
    }

    /**
     * @return bool
     */
    public function valid()
    {
        foreach (static::$required as $required) {
            if (!$this->{$required}) {
                $this->_error = 'Missing required field: ' . $required;
                return false;
            }
        }

        return true;
    }

    /** @return string */
    public function getError(): string
    {
        return $this->_error;
    }

    /**
     * @return string
     */
    public function getSshCommand(): string
    {
        /** @var string */
        $cmd = 'ssh ';
        if ($this->username) {
            $cmd .= "{$this->username}@";
        }
        $cmd .= "{$this->host} ";

        if ($this->port && $this->port != 22) {
            $cmd .= "-p {$this->port}";
        }

        return $cmd;
    }

    /**
     * @return string
     */
    public function getRemoteDumpCommand(?string $verbosityLevel = 'normal'): string
    {
        /** @var Settings */
        $settings = SyncDb::$instance->getSettings();

        /** @var string */
        $dumpMysqlCommand = $this->root . '/' . $settings->remoteDumpCommand . " " . ($verbosityLevel ?? '');
        $cmd = $this->getSshCommand();
        $cmd .= " \"{$this->phpPath} {$dumpMysqlCommand}\"";

        return $cmd;
    }

    /**
     * @param string $filename
     * @param string $localPath
     * @return string
     */
    public function getRemoteDownloadCommand(string $filename, string $localPath): string
    {
        /** @var string */
        $cmd = "scp ";
        if ($this->port && $this->port != 22) {
            $cmd .= "-P {$this->port} ";
        }
        if ($this->username) {
            $cmd .= "{$this->username}@";
        }
        $cmd .= "{$this->host}:";

        $cmd .= "{$this->backupDirectory}{$filename} {$localPath}";

        return $cmd;
    }

    /**
     * @param string $filename
     * @return string
     */
    public function getRemoteDeleteCommand(string $filename): string
    {
        /** @var string */
        $cmd = $this->getSshCommand();
        $cmd .= " rm {$this->backupDirectory}{$filename}";

        return $cmd;
    }
}
