<?php

namespace abryrath\syncdb;

use abryrath\syncdb\models\Settings;
use abryrath\syncdb\LocalCommands;
use abryrath\syncdb\util\Util;
use abryrath\syncdb\util\Logger;

class SyncDb
{
    public static $instance;
    private $settings;

    public function __construct($opts = [])
    {
        static::$instance = $this;
        $this->settings = Settings::parse($opts);
    }

    public function dump(Logger $logger = null)
    {
        Util::checkBackupPath();

        $steps = [
            LocalCommands::mysqlDumpCommand(),
            LocalCommands::tarCommand(),
            LocalCommands::rmCommand(),
        ];

        foreach ($steps as $cmd) {
            Util::exec($cmd);
        }
    }

    public function sync(Logger $logger, $environment = 'production')
    {
        $settings = static::$instance->getSettings();

        if (!$settings->valid()) {
            die();
        }

        Util::checkBackupPath();

        $remote = $settings->environments[$environment];

        $steps = [
            [
                'timing' => 'remote dump',
                'cmd' => $remote->getRemoteDumpCommand(),
            ],
            [
                'timing' => 'remote download',
                'cmd' => $remote->getRemoteDownloadCommand(
                    $settings->sqlDumpFileTarball,
                    $settings->sqlDumpPath()
                ),
            ],
            [
                'cmd' => $remote->getRemoteDeleteCommand($settings->sqlDumpFileTarball),
                'log' => 'Remote file deleted',
            ],
            [
                'cmd' => LocalCommands::extractCommand(),
                'log' => 'Tarball extracted',
            ],
            [
                'cmd' => LocalCommands::importCommand(),
                'log' => 'Local dump complete',
            ],
            [
                'cmd' => 'rm ' . $settings->sqlDumpPath(true, $settings->sqlDumpFileName),
            ],
            [
                'cmd' => 'rm ' . $settings->sqlDumpPath(true, $settings->sqlDumpFileTarball),
            ],
        ];

        foreach ($steps as $step) {
            $cmd = $step['cmd'];
            $timing = $step['timing'] ?? false;
            $log = $step['log'] ?? false;

            $startTime = null;
            $endTime = null;
            if ($timing && $logger) {
                $logger->log("Beginning {$timing}");
                $startTime = microtime(true);
            }

            Util::exec($cmd);

            if ($timing && $logger) {
                $endTime = microtime(true);
                $diffTime = number_format(($endTime - $startTime), 2);
                $logger->log("Task {$timing} completed in {$diffTime} seconds" . PHP_EOL);
            }

            if ($log && $logger) {
                $logger->log($log . PHP_EOL);
            }

            sleep(1);
        }
    }

    public function getSettings()
    {
        return $this->settings;
    }
}
