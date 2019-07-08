<?php

namespace unionco\syncdb\models;

use Exception;
use unionco\syncdb\models\Environment;
use unionco\syncdb\util\Util;
use Symfony\Component\Console\Output\Output;

class Settings
{
    /** @var string */
    public $mysqlClientPath = '';

    /** @var string */
    public $mysqlDumpPath = '';

    /** @var array<Environment> */
    public $environments = [];

    /** @var string */
    public $baseDir = '';

    /** @var string */
    public $storagePath = '';

    /** @var string */
    public $backupDirectory = '';

    /** @var string */
    public $sqlDumpFileName = '';

    /** @var string */
    public $sqlDumpFileTarball = '';

    /** @var string */
    public $remoteDumpCommand = '';

    /** @var int */
    public $verbosity = Output::VERBOSITY_VERBOSE;
    
    /** @var string[] */
    public $ignoredTables = [];

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

    public static function parse(array $opts): Settings
    {
        $settings = new Settings();
        // var_dump($opts); die;
        $settings->mysqlClientPath = $opts['mysqlClientPath'] ?? '';
        $settings->mysqlDumpPath = $opts['mysqlDumpPath'] ?? '';
        $settings->storagePath = $opts['storagePath'] ?? '';
        $settings->baseDir = $opts['baseDir'] ?? '';
        $settings->backupDirectory = $opts['backupDirectory'] ?? 'backups/databases/';
        $settings->sqlDumpFileName = $opts['sqlDumpFileName'] ?? 'db_dump.sql';
        $settings->sqlDumpFileTarball = $opts['sqlDumpFileTarball'] ?? $settings->sqlDumpFileName . '.tar.gz';
        $settings->remoteDumpCommand = $opts['remoteDumpCommand'] ?? '';
        $settings->verbosity = $opts['verbosity'] ?? Output::VERBOSITY_VERBOSE;
        // $settings->ignoredTables = $opts['ignoredTables'] ?? [];
        $settings->parseGlobals($opts['environments'] ?? []);
        $settings->parseEnvironments($opts['environments'] ?? []);
// var_dump($settings); die;
        return $settings;
    }

    /**
     * @param array|string $environments
     * @return void
     */
    private function parseEnvironments($environments)
    {
        if (is_string($environments)) {
            /** @psalm-suppress UnresolvableInclude */
            $environments = (require $environments)['remotes'];
        }

        foreach ($environments as $name => $values) {
            $env = new Environment();
            $env->setName($name);
            $env->readConfig($values);

            $this->environments[$name] = $env;
        }
    }

    private function parseGlobals($environments)
    {
        if (is_string($environments)) {
            /** @psalm-suppress UnresolvableInclude */
            $globals = (require $environments)['globals'];
        } else {
            return;
        }

        foreach ($globals as $name => $values) {
            $this->{$name} = $values;
        }
    }

    /**
     * @return false|string
     */
    public function getMysqlClientPath()
    {
        // Check for explicit path in .env
        /** @var null|bool|string */
        $path = Util::env('MYSQL_CLIENT_PATH');
        if ($path) {
            $path = (string) $path;
            if (Util::checkExecutable($path)) {
                return $path;
            }
        } else {
            foreach (['/usr/bin/mysql', '/usr/local/bin/mysql'] as $path) {
                if (Util::checkExecutable($path)) {
                    return $path;
                }
            }
        }

        return false;
    }

    /**
     * @return false|string
     */
    public function getMysqlDumpPath()
    {
        // Check for explicit path in .env
        /** @var null|bool|string */
        $path = Util::env('MYSQL_DUMP_PATH');

        if ($path) {
            $path = (string) $path;
            if (Util::checkExecutable($path)) {
                return $path;
            }
        }
        // Check some common locations
        foreach (['/usr/bin/mysqldump', '/usr/local/bin/mysqldump'] as $path) {
            if (Util::checkExecutable($path)) {
                return $path;
            }
        }

        return false;
    }

    public function sqlDumpPath(bool $file = true, string $fileName = ''): string
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
