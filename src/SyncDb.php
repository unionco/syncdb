<?php

namespace abryrath\syncdb;

use abryrath\syncdb\LocalCommands;
use abryrath\syncdb\models\Settings;
use abryrath\syncdb\util\Logger;
use abryrath\syncdb\util\Util;

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
            new Command([
                'timing' => 'mysqldump',
                'cmd' => LocalCommands::mysqlDumpCommand(),
            ]),
            new Command([
                'log' => 'Creating tarball archive',
                'cmd' => LocalCommands::tarCommand(),
            ]),
            new Command([
                'log' => 'Removing temporary files',
                'cmd' => LocalCommands::rmCommand(),
            ]),
        ];

        foreach ($steps as $step) {
            Util::exec($step);
        }
    }

    public function sync(Logger $logger = null, $environment = 'production')
    {
        $settings = static::$instance->getSettings();

        if (!$settings->valid()) {
            die();
        }

        Util::checkBackupPath();

        $remote = $settings->environments[$environment];

        $steps = [
            new Command([
                'timing' => 'remote dump',
                'cmd' => $remote->getRemoteDumpCommand(),
            ]),
            new Comand([
                'timing' => 'remote download',
                'cmd' => $remote->getRemoteDownloadCommand(
                    $settings->sqlDumpFileTarball,
                    $settings->sqlDumpPath(true, $settings->sqlDumpFileTarball)
                ),
            ]),
            new Command([
                'cmd' => $remote->getRemoteDeleteCommand($settings->sqlDumpFileTarball),
                'log' => 'Remote file deleted',
            ]),
            new Command([
                'cmd' => LocalCommands::extractCommand(),
                'log' => 'Tarball extracted',
            ]),
            new Command([
                'cmd' => LocalCommands::importCommand(),
                'log' => 'Local dump complete',
            ]),
            new Command([
                'cmd' => 'rm ' . $settings->sqlDumpPath(true, $settings->sqlDumpFileName),
            ]),
            new Command([
                'cmd' => 'rm ' . $settings->sqlDumpPath(true, $settings->sqlDumpFileTarball),
            ]),
        ];

        foreach ($steps as $step) {
            Util::exec($cmd);
        }
    }

    public function getSettings()
    {
        return $this->settings;
    }
}
