<?php

namespace unionco\syncdb\Service;

// use unionco\syncdbFacade;
use unionco\syncdb\Model\Step;
use unionco\syncdb\Model\SshInfo;
use unionco\syncdb\Model\Scenario;
use unionco\syncdb\Service\Config;
use unionco\syncdb\Service\Logger;
use unionco\syncdb\Model\SetupStep;
use unionco\syncdb\Service\Postgres;
use Symfony\Component\Process\Process;
use unionco\syncdb\Model\DatabaseInfo;
use unionco\syncdb\Model\ScenarioStep;
use unionco\syncdb\Model\TeardownStep;

class DatabaseSync
{
    private $logger;

    /**
     * Maintain a list of strings that should not show up in logs/CLI
     * @var string[]
     */
    private static $stringsToScramble = [];

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public static function addStringToScramble(string $string)
    {
        static::$stringsToScramble[] = $string;
    }

    /**
     * Run the full database sync (dump, download, import)
     * @param array $config
     * @param string $environment
     * @return
     */
    public function syncDatabase(array $config, string $environment)
    {
        [$config, $ssh, $remoteDb, $localDb] = self::parseConfigAndDatabases($config, $environment);

        $scenario = new Scenario('Sync Database', $ssh);
        $scenario = $this->dumpDatabase($scenario, $remoteDb);
        $scenario = $this->importDatabase($scenario, $localDb);

        return $scenario->run();
    }

    /**
     * Return each of the config options without running a sync
     * @return array{config:array<array-key,mixed>,ssh:SshInfo,remoteDb:DatabaseInfo,localDb:DatabaseInfo}
     */
    public function dumpConfig(array $config, string $environment)
    {
        [$config, $ssh, $remoteDb, $localDb] = self::parseConfigAndDatabases($config, $environment);
        return compact('config', 'ssh', 'remoteDb', 'localDb');
    }

    /**
     * Return the scenario without running a sync
     * @return Scenario
     */
    public function preview(array $config, string $environment): Scenario
    {
        [$config, $ssh, $remoteDb, $localDb] = self::parseConfigAndDatabases($config, $environment);

        $scenario = new Scenario('Sync Database', $ssh);
        $scenario = $this->dumpDatabase($scenario, $remoteDb);
        $scenario = $this->importDatabase($scenario, $localDb);

        return $scenario;
    }

    /**
     * @return array{array<array-key,mixed>,SshInfo,DatabaseInfo,DatabaseInfo}
     */
    private static function parseConfigAndDatabases(array $config, string $environment)
    {
        [
            'remote' => $remoteConfig,
            'local' => $localConfig,
        ] = Config::parseConfig($config, $environment);

        // var_dump($config); die;
        if (!$remoteConfig) {
            throw new \Exception('Remote Config is invalid');
        } elseif (!$localConfig) {
            throw new \Exception('Local Config is invalid');
        }

        $ssh = SshInfo::fromConfig($remoteConfig);
        $remoteDb = DatabaseInfo::remoteFromConfig($remoteConfig, $ssh);
        $localDb = DatabaseInfo::localFromConfig($localConfig);

        $remoteDbPassword = $remoteDb->getPass();
        DatabaseSync::addStringToScramble($remoteDbPassword);
        $localDbPassword = $localDb->getPass();
        DatabaseSync::addStringToScramble($localDbPassword);


        return [$config, $ssh, $remoteDb, $localDb];
    }
  /**
     *
     * Run a command on the server described by SshInfo $ssh
     * @param SshInfo $ssh
     * @param Step $step
     * @return string|false
     */
    public function runRemote(SshInfo $ssh, Step $step)
    {
        $cmd = $step->getCommandString($ssh);
        $this->logger->info(__METHOD__, [
            'name' => $step->getName(),
            'commandString' => static::scramble($cmd),
        ]);

        $proc = Process::fromShellCommandline($cmd);

        try {
            $proc->mustRun();
        } catch (\Throwable $e) {
            // $this->logger->error($e);
            $this->logger->error($e->getMessage());
            throw $e;
        }
        $errors = $proc->getErrorOutput();
        if ($errors) {
            $this->logger->error(__METHOD__, ['errors' => $errors]);
            return false;
        }
        $output = $proc->getOutput();
        $this->logger->debug(__METHOD__, ['output' => $output]);
        return $output;
    }

    /**
     * @todo consolidate this with runRemote
     *
     * Run a command locally
     * @param Step $step
     * @return string|false
     */
    public function runLocal(Step $step)
    {
        $cmd = $step->getCommandString();
        $this->logger->info(__METHOD__, [
            'name' => $step->getName(),
            'commandString' => $cmd
        ]);

        $proc = Process::fromShellCommandline($cmd);

        try {
            $proc->mustRun();
        } catch (\Throwable $e) {
            // $this->logger->error(__METHOD__, ['errors' => $e]);
            $this->logger->error($e->getMessage());
            throw $e;
        }
        $errors = $proc->getErrorOutput();
        if ($errors) {
            $this->logger->error(__METHOD__, ['errors' => $errors]);
            return false;
        }
        $output = $proc->getOutput();
        $this->logger->debug(__METHOD__, ['output' => $output]);
        return $output;
    }

    /**
     * Add steps to the given scenario to dump the remote database
     * @param Scenario $scenario
     * @param DatabaseInfo $db
     * @return Scenario
     */
    private function dumpDatabase(Scenario $scenario, DatabaseInfo $db)
    {

        $driver = $db->getDriver();
        $this->logger->debug('dumpDatabase - driver: ', ['driver' => $driver]);

        switch ($driver) {
            case 'mysql':
                $scenario = Mysql::dumpDatabase($scenario, $db);
                break;
            case 'pgsql':
                $scenario = Postgres::dumpDatabase($scenario, $db);
                break;
            default:
                throw new \Exception('Invalid driver');
        }

        return $scenario;
    }


    /**
     * Add steps to the given scenario to import the downloaded database
     * @param Scenario $scenario
     * @param DatabaseInfo $localDb
     * @return Scenario
     */
    private function importDatabase(Scenario $scenario, DatabaseInfo $localDb)
    {
        $driver = $localDb->getDriver();
        $this->logger->debug('importDatabase - driver: ', ['driver' => $driver]);

        switch ($driver) {
            case 'mysql':
                $scenario = Mysql::importDatabase($scenario, $localDb);
                break;
            case 'pgsql':
                $scenario = Postgres::importDatabase($scenario, $localDb);
                break;
            default:
                throw new \Exception('Invalid driver');
        }

        return $scenario;
    }

    public static function scramble(string $text): string
    {
        $output = $text;
        foreach (static::$stringsToScramble as $pattern) {
            $len = \strlen($pattern);
            $replace = \str_repeat("*", $len);
            $output = \str_replace($pattern, $replace, $output);
        }
        return $output;
    }
}
