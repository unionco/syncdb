<?php

namespace unionco\syncdb\util;

use Dotenv\Dotenv;
use Psr\Log\LoggerInterface;
use unionco\syncdb\SyncDb;

class Util
{
    /** @var bool */
    private static $loaded = false;

    /**
     * @return void
     */
    private static function loadEnv()
    {
        $baseDir = SyncDb::$instance->getSettings()->baseDir;
        if ($baseDir) {
            if (!static::$loaded) {
                (new Dotenv($baseDir))->load();
                static::$loaded = true;
            }
        }
    }

    /**
     * @return void
     */
    public static function checkBackupPath()
    {
        $backupPath = SyncDb::$instance->getSettings()
            ->sqlDumpPath(false);

        if (!file_exists($backupPath)) {
            mkdir($backupPath, 0777, true);
        }
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return bool|null|string
     */
    public static function env(string $key, $default = null)
    {
        static::loadEnv();

        // first try normal env
        $value = getenv($key);

        // if normal env is false, try site specific env
        if ($value === false) {
            return $default;
        }

        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;

            case 'false':
            case '(false)':
                return false;

            case 'empty':
            case '(empty)':
                return '';

            case 'null':
            case '(null)':
                return null;
        }

        return $value;
    }

    public static function checkExecutable(string $path): bool
    {
        // /** @var string  */
        // $cmd = "bash -c 'which {$path}'";
        
        // /** @var array */
        // $output = [];

        // /** @var int */
        // $returnVar = 0;

        // exec($cmd, $output, $returnVar);

        // if ($returnVar != 0) {
        //     return false;
        // }

        // return true;
        return \is_executable($path);
    }

    /**
     * @param null|string $path
     * @return string
     */
    public static function storagePath($path = null)
    {
        $storagePath = SyncDb::$instance->getSettings()->storagePath;
        if ($path) {
            return $storagePath . '/' . trim($path, '/');
        }

        return $storagePath . '/';
    }

    /**
     * @param Command $command
     * @param LoggerInterface $logger
     * @return void
     */
    public static function exec(Command $command, LoggerInterface $logger, bool $remote = false)
    {
        /** @var bool */
        $silent = true;

        /** @var bool */
        $failOnError = false;

        /** @var array */
        $output = [];

        /** @var int */
        $returnVar = 0;

        /** @var string */
        $cmd = $command->getCommand();

        /** @var string */
        $scrubbed = $command->getScrubbedCommand();

        $name = $command->getName();

        /** @var bool */
        $timed = $command->getTimed();

        /** @var float */
        $startTime = 0.0;

        /** @var float */
        $endTime = 0.0;

        /**
         * Show a prefix of '[REMOTE]' if this command is run on the remote server
         * @var string $prefix
         */
        $prefix = $remote ? '[REMOTE] ' : '';

        $logger->info("Beginning {$name}");
        
        if ($timed) {
            $startTime = microtime(true);
        }

        if ($silent) {
            $cmd = $cmd . " 2>&1";
        }
        if ($logger) {
            $logger->debug($prefix . $scrubbed);
        }

        exec($cmd, $output, $returnVar);

        foreach ($output as $line) {
            if ($logger) {
                $logger->debug($prefix . $line);
            }
        }

        if ($returnVar != 0) {
            if ($logger) {
                $logger->error($prefix . "return non-zero: {$returnVar}");
                $logger->error($prefix . "Failed on command: {$scrubbed}");
            
                foreach ($output as $line) {
                    $logger->error($prefix . $line);
                }
            }
            if ($failOnError) {
                die;
            }
        }

        if ($timed) {
            $endTime = microtime(true);
            $diffTime = number_format(($endTime - $startTime), 2);
            $logger->debug($prefix . "Task {$name} completed in {$diffTime} seconds");
        }
    }
}
