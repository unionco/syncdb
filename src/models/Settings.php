<?php

namespace unionco\syncdb\models;

use Exception;
use unionco\syncdb\models\Environment;
use unionco\syncdb\util\Util;
use Symfony\Component\Console\Output\Output;

abstract class Settings
{
    public static $envVarClientPath = '';
    public static $envVarDumpClientPath = '';
    public static $defaultClientPaths = [];
    public static $defaultDumpClientPaths = [];

    /** @var Environment[] */
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
    public $skipTables = [];

    /** @var string */
    public $dbClientPath = '';

    /** @var string */
    public $dbDumpPath = '';

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

        return true;
    }

    public static function parse(array $opts, Settings $settings = null): Settings
    {
        $settings->storagePath = $opts['storagePath'] ?? '';
        $settings->baseDir = $opts['baseDir'] ?? '';
        $settings->backupDirectory = $opts['backupDirectory'] ?? 'backups/databases/';
        $settings->sqlDumpFileName = $opts['sqlDumpFileName'] ?? 'db_dump.sql';
        $settings->sqlDumpFileTarball = $opts['sqlDumpFileTarball'] ?? $settings->sqlDumpFileName . '.tar.gz';
        $settings->remoteDumpCommand = $opts['remoteDumpCommand'] ?? '';
        $settings->verbosity = $opts['verbosity'] ?? Output::VERBOSITY_VERBOSE;
        $settings->parseGlobals($opts['environments'] ?? []);
        $settings->parseEnvironments($opts['environments'] ?? []);

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

    /**
     * @return false|string
     */
    public function getDbClientPath()
    {
        // Check for explicit path in .env
        /** @var null|bool|string */
        $path = Util::env(static::$envVarClientPath);
        if ($path) {
            $path = (string) $path;
            if (Util::checkExecutable($path)) {
                return $path;
            }
        } else {
            foreach (static::$defaultClientPaths as $path) {
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
    public function getDbDumpClientPath()
    {
        // Check for explicit path in .env
        /** @var null|bool|string */
        $path = Util::env(static::$envVarDumpClientPath);

        if ($path) {
            $path = (string) $path;
            if (Util::checkExecutable($path)) {
                return $path;
            }
        }
        // Check some common locations
        foreach (static::$defaultDumpClientPaths as $path) {
            if (Util::checkExecutable($path)) {
                return $path;
            }
        }

        return false;
    }

    /**
     * @param string $environmentName
     * @return false|Environment
     */
    public function getEnvironment($environmentName)
    {
        $matches = array_filter(
            $this->environments,
            /**
             * @param Environment $env
             * @return bool
             */
            function ($env) use ($environmentName) {
                return $env->name === $environmentName;
            }
        );

        if ($matches) {
            return array_pop($matches);
        }
        return false;
    }
}
