<?php

namespace unionco\syncdb;

use Psr\Log\LoggerInterface;
use unionco\syncdb\util\Util;
use unionco\syncdb\util\Command;
use unionco\syncdb\LocalCommands;
use unionco\syncdb\models\Settings;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Formatter\OutputFormatter;

class SyncDb
{
    /** @var SyncDb $instance */
    public static $instance;

    /** @var Settings */
    private $settings;
    
    /** @var bool Indicates if there is a sync active */
    private $_running = false;

    private $_success = false;

    private $_logger;

    /**
     * @param array $opts
     */
    public function __construct($opts = [])
    {
        static::$instance = $this;
        $this->settings = Settings::parse($opts);
    }

    /**
     * @param LoggerInterface $logger
     * @return bool
     */
    public function dump(LoggerInterface $logger = null)
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

        if ($logger === null) {
            $output = new ConsoleOutput();
            $logger = new ConsoleLogger($output);
        }

        foreach ($steps as $step) {
            Util::exec($step, $logger);
        }

        return true;
    }

    /**
     * @param LoggerInterface $logger
     * @param string $environment
     * @return bool
     */
    public function sync(LoggerInterface $logger = null, $environment = 'production', bool $background = false)
    {
        $this->_logger = $logger;
        $this->_success = false;
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
            new Command([
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

        $this->_running = true;
        if ($logger === null) {
            $output = new ConsoleOutput($settings->verbosity ?? Output::VERBOSITY_DEBUG, true);
            $logger = new ConsoleLogger($output);
        }

        $logger->notice("Starting database sync");

        foreach ($steps as $step) {
            try {
                Util::exec($step, $logger);
            } catch (\Exception $e) {
                $this->_running = false;
                $logger->logOutput(print_r($e->getMessage(), true));
            }
        }
        $this->_running = false;
        $this->_success = true;

        $logger->notice("Database sync complete");

        return true;
    }

    /**
     * @return Settings
     */
    public function getSettings()
    {
        return $this->settings;
    }

    public function running(): bool
    {
        return $this->_running;
    }

    public function success(): bool
    {
        return $this->_success;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->_logger;
    }
}
