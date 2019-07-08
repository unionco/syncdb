<?php

namespace unionco\syncdb;

use unionco\syncdb\util\Util;

class LocalCommands
{
    /**
     * @return string
     */
    public static function mysqlDumpCommand()
    {
        /** @var \unionco\syncdb\models\Settings */
        $settings = SyncDb::$instance->getSettings();
        
        /** @var string */
        $mysqlDumpPath = $settings->getMysqlDumpPath();
        
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

        if ($settings->ignoredTables) {
            $logger = SyncDb::$instance->getLogger();
            foreach ($settings->ignoredTables as $tableName) {
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

    /**
     * @return string
     */
    public static function importCommand(): string
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
