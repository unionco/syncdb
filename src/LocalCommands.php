<?php

namespace abryrath\syncdb;

use abryrath\syncdb\util\Util;

class LocalCommands
{
    public static function mysqlDumpCommand()
    {
        $settings = SyncDb::$instance->getSettings();
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

        if ($dumpPath = $settings->sqlDumpPath()) {
            $cmd .= " > {$dumpPath}";
        }

        return $cmd;
    }

    public static function importCommand(): string
    {
        $settings = SyncDb::$instance->getSettings();
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

    public static function tarCommand(): string
    {
        $cmd = '';
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
        $settings = SyncDb::$instance->getSettings();
        $cmd = "cd ";
        $cmd .= $settings->sqlDumpPath(false);
        $cmd .= " && tar -xzvf ";
        $cmd .= $settings->sqlDumpFileTarball;

        return $cmd;
    }

    public static function rmCommand(): string
    {
        $cmd = '';
        $settings = SyncDb::$instance->getSettings();

        if ($dumpPath = $settings->sqlDumpPath()) {
            $cmd = "rm {$dumpPath}";
        }

        return $cmd;
    }
}
