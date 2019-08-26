<?php

namespace unionco\syncdb;

use unionco\syncdb\util\Util;

class LocalCommands
{
    public static function dumpCommand(string $driver)
    {
        switch ($driver) {
            case SyncDb::DRIVER_MYSQL:
                return static::mysqlDumpCommand();
            case SyncDb::DRIVER_PGSQL:
                return static::pgsqlDumpCommand();
        }
    }

    /**
     * Return a string of the form:
     * `mysqldump -h<host> -P<port> -u <user> --password="<pass>" <database> [<options>] > <dumpPath>`
     *
     * @return string
     */
    protected static function mysqlDumpCommand()
    {
        /** @var \unionco\syncdb\models\Settings */
        $settings = SyncDb::$instance->getSettings();

        /** @var string */
        $mysqlDumpPath = $settings->getDbDumpClientPath();

        if (!$mysqlDumpPath) {
            throw new \Exception('mysqldump executable not found');
        }

        $cmd = "{$mysqlDumpPath} ";
        if ($dbServer = Util::env('DB_SERVER')) {
            $cmd .= "-h {$dbServer} ";
        }
        if ($dbPort = Util::env('DB_PORT')) {
            $cmd .= "-P {$dbPort} ";
        }
        if ($dbUser = Util::env('DB_USER')) {
            $cmd .= "-u {$dbUser} ";
        }
        if ($dbPassword = Util::env('DB_PASSWORD')) {
            $cmd .= "--password=\"{$dbPassword}\" ";
        }
        if ($dbDatabase = Util::env('DB_DATABASE')) {
            $cmd .= "{$dbDatabase} ";
        }

        if ($settings->skipTables) {
            $logger = SyncDb::$instance->getLogger();
            foreach ($settings->skipTables as $tableName) {
                $name = "{$dbDatabase}.{$tableName}";
                $logger->debug("Adding ignored table: {$name}");
                $cmd .= "--ignore-table={$name} ";
            }
        }

        if ($dumpPath = $settings->sqlDumpPath()) {
            $cmd .= " > {$dumpPath}";
        }

        return $cmd;
    }

    protected static function pgsqlDumpCommand()
    {
        /** @var \unionco\syncdb\models\Settings */
        $settings = SyncDb::$instance->getSettings();

        /** @var string */
        $pgDumpPath = $settings->getDbDumpClientPath();

        if (!$pgDumpPath) {
            throw new \Exception('mysqldump executable not found');
        }

        $cmd = "{$pgDumpPath} ";
        if ($dbServer = Util::env('DB_SERVER')) {
            $cmd .= "-h {$dbServer} ";
        }
        if ($dbPort = Util::env('DB_PORT')) {
            $cmd .= "-p {$dbPort} ";
        }
        if ($dbUser = Util::env('DB_USER')) {
            $cmd .= "-U {$dbUser} ";
        }
        if ($dbPassword = Util::env('DB_PASSWORD')) {
            $cmd = "/usr/bin/env PG_PASSWORD=\"{$dbPassword}\" {$cmd}";
        }
        if ($dbDatabase = Util::env('DB_DATABASE')) {
            $cmd .= "{$dbDatabase} ";
        }

        $cmd .= " --no-acl --if-exists --clean --no-owner --no-privileges";

        if ($dumpPath = $settings->sqlDumpPath()) {
            $cmd .= " > {$dumpPath}";
        }

        return $cmd;
    }

    /**
     * @return string
     */
    public static function importCommand(string $driver): string
    {
        switch ($driver) {
            case SyncDb::DRIVER_MYSQL:
                return static::mysqlImportCommand();
            case SyncDb::DRIVER_PGSQL:
                return static::pgsqlImportCommand();
        }
    }

    protected static function mysqlImportCommand(): string
    {
        /** @var \unionco\syncdb\models\Settings */
        $settings = SyncDb::$instance->getSettings();

        /** @var string */
        $cmd = "mysql ";

        if ($dbUser = Util::env('DB_USER')) {
            $cmd .= "-u {$dbUser} ";
        }
        if ($dbServer = Util::env('DB_SERVER')) {
            $cmd .= "-h {$dbServer} ";
        }
        if ($dbPort = Util::env('DB_PORT')) {
            $cmd .= "-P {$dbPort} ";
        }
        if ($dbPassword = Util::env('DB_PASSWORD')) {
            $cmd .= "--password=\"{$dbPassword}\" ";
        }
        if ($dbDatabase = Util::env('DB_DATABASE')) {
            $cmd .= "{$dbDatabase} ";
        }

        if ($dumpPath = $settings->sqlDumpPath()) {
            $cmd .= " < {$dumpPath}";
        }

        return $cmd;
    }

    protected static function pgsqlImportCommand(): string
    {
        /** @var \unionco\syncdb\models\Settings */
        $settings = SyncDb::$instance->getSettings();

        /** @var string */
        $cmd = "psql ";

        if ($dbServer = Util::env('DB_SERVER')) {
            $cmd .= "-h {$dbServer} ";
        }
        if ($dbPort = Util::env('DB_PORT')) {
            $cmd .= "-p {$dbPort} ";
        }
        if ($dbUser = Util::env('DB_USER')) {
            $cmd .= "-U {$dbUser} ";
        }
        if ($dbPassword = Util::env('DB_PASSWORD')) {
            $cmd = "/usr/bin/env PG_PASSWORD=\"{$dbPassword}\" {$cmd}";
        }
        if ($dbDatabase = Util::env('DB_DATABASE')) {
            $cmd .= "{$dbDatabase} ";
        }

        if ($dumpPath = $settings->sqlDumpPath()) {
            $cmd .= " < {$dumpPath}";
        }

        return $cmd;
    }

    /**
     * @return string
     */
    public static function tarCommand(): string
    {
        /** @var string */
        $cmd = '';

        /** @var \unionco\syncdb\models\Settings */
        $settings = SyncDb::$instance->getSettings();

        if ($dumpPath = $settings->sqlDumpPath(false)) {
            $cmd = "cd {$dumpPath} && tar -czvf ";
            $cmd .= $settings->sqlDumpFileTarball;
            $cmd .= " ";
            $cmd .= $settings->sqlDumpFileName;
        }

        return $cmd;
    }

    public static function extractCommand(): string
    {
        /** @var \unionco\syncdb\models\Settings */
        $settings = SyncDb::$instance->getSettings();

        /** @var string */
        $cmd = "cd ";
        $cmd .= $settings->sqlDumpPath(false);
        $cmd .= " && tar -xzvf ";
        $cmd .= $settings->sqlDumpFileTarball;

        return $cmd;
    }

    public static function rmCommand(): string
    {
        /** @var string */
        $cmd = '';

        /** @var \unionco\syncdb\models\Settings */
        $settings = SyncDb::$instance->getSettings();

        if ($dumpPath = $settings->sqlDumpPath()) {
            $cmd = "rm {$dumpPath}";
        }

        return $cmd;
    }
}
