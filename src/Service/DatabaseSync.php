<?php

namespace unionco\syncdb\Service;

// use unionco\syncdbFacade;
use unionco\syncdb\Model\Step;
use unionco\syncdb\Model\SshInfo;
use unionco\syncdb\Model\Scenario;
use unionco\syncdb\Service\Config;
use unionco\syncdb\Service\Logger;
use unionco\syncdb\Model\SetupStep;
use Symfony\Component\Process\Process;
use unionco\syncdb\Model\DatabaseInfo;
use unionco\syncdb\Model\ScenarioStep;
use unionco\syncdb\Model\TeardownStep;

class DatabaseSync
{
    private $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
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

    public function dumpConfig(array $config, string $environment)
    {
        [$config, $ssh, $remoteDb, $localDb] = self::parseConfigAndDatabases($config, $environment);
        return compact('config', 'ssh', 'remoteDb', 'localDb');
    }

    /**
     * @return array{array,SshInfo,DatabaseInfo,DatabaseInfo}
     */
    private static function parseConfigAndDatabases(array $config, string $environment)
    {
        $config = Config::parseConfig($config, $environment);
        $ssh = SshInfo::fromConfig($config);
        $remoteDb = DatabaseInfo::remoteFromConfig($config, $ssh);
        $localDb = DatabaseInfo::localFromConfig($config);

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
            'commandString' => $cmd,
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
    public function dumpDatabase(Scenario $scenario, DatabaseInfo $db)
    {
        $driver = strtolower($db->getDriver());
        $this->logger->debug('dumpDatabase - driver: ', ['driver' => $driver]);
        $ssh = $scenario->getSshContext();

        if (strpos($driver, 'mysql') !== false) {

            // Setup a remote config file, which allows using mysqldump without passwords on the CLI/ENV
            $setupRemoteMysqlCredentials = new SetupStep(
                'Setup Remote MySQL Credentials',
                $this->mysqlCredentialCommands($db->getUser(), $db->getPass())
            );
            $teardownRemoteCredentials = new TeardownStep(
                'Teardown Remote MySQL Credentials',
                [
                    'rm ~/.mysql/syncdb.cnf',
                ],
                $setupRemoteMysqlCredentials
            );
            $scenario
                ->addSetupStep($setupRemoteMysqlCredentials)
                ->addTeardownStep($teardownRemoteCredentials);

            // Dump the database to a temporary location
            $chainDump = (new ScenarioStep('MySQL Dump', true))
                ->setCommands([
                    "mysqldump --defaults-extra-file=~/.mysql/syncdb.cnf -h {$db->getHost()} -P {$db->getPort()} {$db->getName()} > {$db->getTempFile()}",
                ]);
            $teardownSql = new TeardownStep(
                'Remove Remote SQL File', ["rm {$db->getTempFile()}"], $chainDump);

            $scenario
                ->addChainStep($chainDump);

            // Archive the remote SQL file using tar/bzip2
            $chainArchive = (new ScenarioStep('Archive', true))
                ->setCommands([
                    "tar cvjf {$db->getArchiveFile(true, true)} -C {$db->getTempDir(true)} {$db->getTempFile(false, true)}",
                ]);

            // Cleanup both the raw SQL file and its related archive
            $teardownArchive = new TeardownStep(
                'Remote Remote Archive File', ["rm {$db->getArchiveFile()}"], $chainArchive);

            $scenario
                ->addChainStep($chainArchive)
                ->addTeardownStep($teardownSql)
                ->addTeardownStep($teardownArchive);

            // Download the file using SCP
            $scpCommand = $ssh->getScpCommand($db->getArchiveFile(true, true), $db->getArchiveFile(true, false));
            $downloadArchive = (new ScenarioStep(
                'Download Archive File',
                false))->setCommands([$scpCommand]);

            $teardownDownload = new TeardownStep(
                'Remove Local Archive File',
                ["rm {$db->getArchiveFile()}"],
                $downloadArchive,
                false
            );

            $scenario
                ->addChainStep($downloadArchive)
                ->addTeardownStep($teardownDownload);
        } elseif (strpos($driver, 'pgsql') !== false) {

        } else {
            throw new \Exception('Invalid driver');
        }

        return $scenario;
    }

    /**
     * Commands to setup the mysql credentials file, ~/.mysql/syncdb.cnf
     * @param string $user
     * @param string $pass
     * @return string[]
     */
    private function mysqlCredentialCommands($user, $pass, $dump = true)
    {
        return [
            'mkdir -p ~/.mysql',
            'chmod 0700 ~/.mysql',
            'if test -f ~/.mysql/syncdb.cnf; then chmod 0600 ~/.mysql/syncdb.cnf; else touch ~/.mysql/syncdb.cnf; fi',
            // 'touch ~/.mysql/syncdb.cnf',
            "echo [" . ($dump ? 'mysqldump' : 'mysql') . "] > ~/.mysql/syncdb.cnf",
            "echo user={$user} >> ~/.mysql/syncdb.cnf",
            "echo password={$pass} >> ~/.mysql/syncdb.cnf",
            'chmod 0400 ~/.mysql/syncdb.cnf',
        ];
    }

    /**
     * Add steps to the given scenario to import the downloaded database
     * @param Scenario $scenario
     * @param DatabaseInfo $localDb
     * @return Scenario
     */
    public function importDatabase(Scenario $scenario, DatabaseInfo $localDb)
    {
        // Setup a config file, used for mysql client
        $setupLocalMysqlCredentials = new SetupStep(
            'Setup Local MySQL Credentials',
            $this->mysqlCredentialCommands($localDb->getuser(), $localDb->getPass(), false),
            false
        );
        $teardownLocalCredentials = new TeardownStep(
            'Teardown Local MySQL Credentials',
            [
                'rm ~/.mysql/syncdb.cnf',
            ],
            $setupLocalMysqlCredentials,
            false
        );

        $scenario->addSetupStep($setupLocalMysqlCredentials)
            ->addTeardownStep($teardownLocalCredentials);

        // Unarchive the file that was downloaded
        $localUnarchive = (new ScenarioStep('Unarchive Local SQL file', false))
            ->setCommands([
                "cd {$localDb->getTempDir(false)}; tar xjf {$localDb->getArchiveFile(false, false)}",
            ]);
        $removeSqlFile = new TeardownStep('Remove Local SQL File', ["rm {$localDb->getTempFile(true, false)}"], $localUnarchive);

        $scenario->addChainStep($localUnarchive)
            ->addTeardownStep($removeSqlFile);

        // Import the SQL file using mysql client
        $import = (new ScenarioStep('Import Database', false))
            ->setCommands([
                "mysql --defaults-file=~/.mysql/syncdb.cnf -h {$localDb->getHost()} -P {$localDb->getPort()} {$localDb->getName()} < {$localDb->getTempFile(true, false)}",
            ]);
        $scenario->addChainStep($import);

        return $scenario;
    }
}
