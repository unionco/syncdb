<?php

namespace unionco\syncdb;

use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;
use unionco\syncdb\util\Util;
use unionco\syncdb\util\Command;
use unionco\syncdb\LocalCommands;
use unionco\syncdb\models\Settings;
use unionco\syncdb\models\MysqlSettings;
use unionco\syncdb\models\PgsqlSettings;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Formatter\OutputFormatter;

class SyncDb
{
    const DRIVER_MYSQL = 'mysql';
    const DRIVER_PGSQL = 'pgsql';

    /** @var SyncDb $instance */
    public static $instance;

    public $driver = self::DRIVER_MYSQL;

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
        
        $driver = $opts['driver'] ?? getenv('DB_DRIVER') ?? false;
        
        if (!$driver) {
            throw new \Exception('Database driver must be set. Supported options are mysql and pgsql');
        }
        $settings = null;
        switch ($driver) {
            case self::DRIVER_MYSQL:
                $settings = new MysqlSettings();
                break;
            case self::DRIVER_PGSQL:
                $settings = new PgsqlSettings();
                break;
            default:
                throw new \Exception('Unsupported driver');
        }
        $this->driver = $driver;
        $this->settings = Settings::parse($opts, $settings);
    }

    private function checkLogger($settings)
    {
        if ($this->_logger === null) {
            // var_dump($settings); die;
            if (!$this->_verbosityLevel) {
                $this->_verbosityLevel = $settings->verbosity ?? Output::VERBOSITY_QUIET;
            }
            // echo "Using verbosityLevel : $this->_verbosityLevel \n";
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
    public function dump(LoggerInterface $logger = null, ?int $verbosityLevel = null, bool $remote = false)
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
                'name' => 'database dump',
                'timed' => true,
                'cmd' => LocalCommands::dumpCommand($this->driver),
            ]),
            new Command([
                'name' => 'create tarball archive',
                'timed' => true,
                'cmd' => LocalCommands::tarCommand(),
            ]),
            new Command([
                'name' => 'remove temporary files',
                'timed' => false,
                'cmd' => LocalCommands::rmCommand(),
            ]),
        ];

        foreach ($steps as $step) {
            Util::exec($step, $this->_logger, $remote);
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

        // $remote = $settings->environments[$environment];
        $remote = $settings->getEnvironment($environment);

        if (!$remote) {
            throw new \Exception('Environment not found: ' . $environment);
        }
        
        $steps = [
            new Command([
                'name' => 'remote dump',
                'timed' => true,
                'cmd' => $remote->getRemoteDumpCommand($verbosityLevel),
            ]),
            new Command([
                'name' => 'remote download',
                'timed' => true,
                'cmd' => $remote->getRemoteDownloadCommand(
                    $settings->sqlDumpFileTarball,
                    $settings->sqlDumpPath(true, $settings->sqlDumpFileTarball)
                ),
            ]),
            new Command([
                'name' => 'delete remote file',
                'timed' => false,
                'cmd' => $remote->getRemoteDeleteCommand($settings->sqlDumpFileTarball),
            ]),
            new Command([
                'name' => 'extract archive',
                'timed' => true,
                'cmd' => LocalCommands::extractCommand(),
            ]),
            new Command([
                'name' => 'import sql',
                'timed' => true,
                'cmd' => LocalCommands::importCommand($this->driver),
            ]),
            new Command([
                'name' => 'remove local sql file',
                'cmd' => 'rm ' . $settings->sqlDumpPath(true, $settings->sqlDumpFileName),
            ]),
            new Command([
                'name' => 'remove local archive',
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
