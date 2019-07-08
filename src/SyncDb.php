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
use Psr\Log\LogLevel;

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
    private $_verbosityLevel;

    /**
     * @param array $opts
     */
    public function __construct($opts = [])
    {
        static::$instance = $this;
        $this->settings = Settings::parse($opts);
    }

    private function checkLogger($settings)
    {
        if ($this->_logger === null) {
            // var_dump($settings); die;
            if (!$this->_verbosityLevel) {
                $this->_verbosityLevel = $settings->verbosity ?? Output::VERBOSITY_QUIET;
            }
            echo "Using verbosityLevel : $this->_verbosityLevel \n";
            $output = new ConsoleOutput($this->_verbosityLevel, true);
            $this->_logger = new ConsoleLogger(
                $output,
                [
                    LogLevel::INFO => Output::VERBOSITY_NORMAL,
                    LogLevel::NOTICE => Output::VERBOSITY_NORMAL,
                    LogLevel::DEBUG => Output::VERBOSITY_VERBOSE,
                    LogLevel::ERROR => Output::VERBOSITY_NORMAL,
                ]
            );
        }
    }

    /**
     * @param LoggerInterface $logger
     * @return bool
     */
    public function dump(LoggerInterface $logger = null, ?int $verbosityLevel = null)
    {
        $this->_logger = $logger;
        $this->_verbosityLevel = $verbosityLevel;
        $settings = static::$instance->getSettings();
        if (!$settings->valid()) {
            throw new \Exception('Settings are invalid');
            die;
        }

        $this->checkLogger($settings);

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

        // if ($logger === null) {
        //     $output = new ConsoleOutput();
        //     $logger = new ConsoleLogger($output);
        // }

        foreach ($steps as $step) {
            Util::exec($step, $this->_logger);
        }

        return true;
    }

    /**
     * @param LoggerInterface $logger
     * @param string $environment
     * @return bool
     */
    public function sync(LoggerInterface $logger = null, $environment = 'production', bool $background = false, ?int $verbosityLevel = null)
    {
        $this->_logger = $logger;
        $this->_success = false;
        $settings = static::$instance->getSettings();

        if (!$settings->valid()) {
            throw new \Exception('Settings are invalid');
            die();
        }

        Util::checkBackupPath();

        $remote = $settings->environments[$environment];

        $steps = [
            new Command([
                'timing' => 'remote dump',
                'cmd' => $remote->getRemoteDumpCommand($verbosityLevel),
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
        $this->checkLogger($settings);

        $this->_logger->notice("Starting database sync");

        foreach ($steps as $step) {
            try {
                Util::exec($step, $this->_logger);
            } catch (\Exception $e) {
                $this->_running = false;
                $this->_logger->logOutput(print_r($e->getMessage(), true));
            }
        }
        $this->_running = false;
        $this->_success = true;

        $this->_logger->notice("Database sync complete");

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
