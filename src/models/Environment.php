<?php

namespace abryrath\syncdb\models;

use abryrath\syncdb\SyncDb;

class Environment
{
    public $name;
    public $host;
    public $port;
    public $username;
    public $phpPath;
    public $root;
    public $backupDirectory;

    public static $required = [
        'name',
        'host',
        'phpPath',
        'root',
    ];

    public function __construct()
    {
        $this->port = 22;
    }

    public function readConfig(array $config): void
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
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setHost(string $host): void
    {
        $this->host = $host;
    }

    public function setPort(int $port): void
    {
        $this->port = $port;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    public function setPhpPath(string $phpPath): void
    {
        $this->phpPath = $phpPath;
    }

    public function setRoot(string $root): void
    {
        $this->root = $root;
    }

    public function setBackupDirectory(string $backupDirectory): void
    {
        if (substr($backupDirectory, strlen($backupDirectory) - 1, 1) != DIRECTORY_SEPARATOR) {
            $backupDirectory .= DIRECTORY_SEPARATOR;
        }
        $this->backupDirectory = $backupDirectory;
    }

    public function valid()
    {
        foreach (static::$required as $required) {
            if (!$this->{$required}) {
                return false;
            }
        }

        return true;
    }

    public function getSshCommand(): string
    {
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

    public function getRemoteDumpCommand(): string
    {
        $settings = SyncDb::$instance->getSettings();
        $dumpMysqlCommand = $this->root . '/' . $settings->remoteDumpCommand;
        //Craftsyncdb::CONSOLE_PREFIX . '/' . Craftsyncdb::DUMP_COMMAND;
        $cmd = $this->getSshCommand();
        $cmd .= " \"{$this->phpPath} {$dumpMysqlCommand}\"";
        //{$this->root}/craft {$dumpMysqlCommand}\"";

        return $cmd;
    }

    public function getRemoteDownloadCommand(string $filename, string $localPath): string
    {
        //var_dump($localPath); die;
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

    public function getRemoteDeleteCommand(string $filename): string
    {
        $cmd = $this->getSshCommand();
        $cmd .= " rm {$this->backupDirectory}{$filename}";

        return $cmd;
    }
}
