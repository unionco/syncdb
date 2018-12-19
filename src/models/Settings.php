<?php

namespace abryrath\syncdb\models;

use abryrath\syncdb\util\Util;
use Exception;

class Settings
{
    public $mysqlClientPath;
    public $mysqlDumpPath;
    public $environments;
    public $baseDir;
    public $storagePath;
    public $backupDirectory;
    public $sqlDumpFileName;
    public $sqlDumpFileTarball;
    public $remoteDumpCommand;

    public function valid(): bool
    {
        if (!$this->environments || !count($this->environments)) {
            throw new Exception('No environments are configured');
        }

        foreach ($this->environments as $env) {
            if (!$env->valid()) {
                throw new Exception($env->getError());
            }
        }

        if (!$this->getMysqlDumpPath()) {
            throw new Exception('Could not find a valid mysqldump executable. Please check your configuration.');
        }

        return true;
    }

    public static function parse(array $opts) : Settings
    {
        $settings = new Settings();

        $settings->mysqlClientPath = $opts['mysqlClientPath'] ?? '';
        $settings->mysqlDumpPath = $opts['mysqlDumpPath'] ?? '';
        $settings->storagePath = $opts['storagePath'] ?? '';
        $settings->baseDir = $opts['baseDir'] ?? '';
        $settings->backupDirectory = $opts['backupDirectory'] ?? 'backups/databases/';
        $settings->sqlDumpFileName = $opts['sqlDumpFileName'] ?? 'db_dump.sql';
        $settings->sqlDumpFileTarball = $opts['sqlDumpFileTarball'] ?? $settings->sqlDumpFileName . '.tar.gz';
        $settings->remoteDumpCommand = $opts['remoteDumpCommand'] ?? '';
        $settings->parseEnvironments($opts['environments'] ?? []);

        return $settings;
    }

    private function parseEnvironments($environments): void
    {
        if (is_string($environments)) {
            $environments = (require $environments)['remotes'];
        }

        foreach ($environments as $name => $values) {
            $env = new Environment();
            $env->setName($name);
            $env->readConfig($values);

            $this->environments[$name] = $env;
        }
    }

    public function getMysqlClientPath(): mixed
    {
        // Check for explicit path in .env
        $path = Util::env('MYSQL_CLIENT_PATH');

        if ($path && Util::checkExecutable($path)) {
            return $path;
        }

        // Check some common locations
        foreach (['/usr/bin/mysqldump', '/usr/local/bin/mysqldump'] as $path) {
            if (Util::checkExecutable($path)) {
                return $path;
            }
        }

        return false;
    }

    public function getMysqlDumpPath()
    {
        // Check for explicit path in .env
        $path = Util::env('MYSQL_DUMP_PATH');

        if ($path && Util::checkExecutable($path)) {
            return $path;
        }

        // Check some common locations
        foreach (['/usr/bin/mysqldump', '/usr/local/bin/mysqldump'] as $path) {
            if (Util::checkExecutable($path)) {
                return $path;
            }
        }

        return false;
    }

    public function sqlDumpPath(bool $file = true, string $fileName = '') : string
    {
        if ($file && !$fileName) {
            return Util::storagePath($this->backupDirectory . $this->sqlDumpFileName);
        } elseif ($file && $fileName) {
            return Util::storagePath($this->backupDirectory . $fileName);
        } else {
            return Util::storagePath($this->backupDirectory);
        }
    }
}
