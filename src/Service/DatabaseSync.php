<?php

namespace unionco\syncdb\Service;

// use unionco\syncdbFacade;
use Monolog\Handler\StreamHandler;
use unionco\syncdb\Service\Logger;
use Symfony\Component\Process\Process;
use unionco\syncdb\Model\DatabaseInfo;
use unionco\syncdb\Model\Scenario;
use unionco\syncdb\Model\ScenarioStep;
use unionco\syncdb\Model\SetupStep;
use unionco\syncdb\Model\SshInfo;
use unionco\syncdb\Model\Step;
use unionco\syncdb\Model\TeardownStep;

// use Symfony\Component\Validator\Validator\ValidatorInterface;

class DatabaseSync
{
    // /** @var ValidatorInterface */
    // protected static $validator;
    private $logger;

    public function __construct(Logger $logger)
    {
        // var_dump($logger); die;
        $this->logger = $logger;
    }

    // private static function info($msg)
    // {
    //     if (static::$logger) {
    //         static::$logger->info($msg);
    //     }
    // }
    public function runRemote(SshInfo $ssh, Step $step)
    {
        $this->logger->info('runRemote: ' . $step->getName());
        $this->logger->info($step->getCommandString($ssh));
        $cmd = $step->getCommandString($ssh);
        $proc = Process::fromShellCommandline($cmd);

        try {
            $proc->mustRun();
        } catch (\Throwable $e) {
            $this->logger->error($e);
            throw $e;
        }
        $errors = $proc->getErrorOutput();
        if ($errors) {
            $this->logger->error($errors);
            return false;
            var_dump($errors); /** @todo */
        }
        return $proc->getOutput();
    }

    public function runLocal(Step $step)
    {
        $cmd = $step->getCommandString();
        $proc = Process::fromShellCommandline($cmd);

        try {
            $proc->mustRun();
        } catch (\Throwable$e) {
            throw $e;
        }
        $errors = $proc->getErrorOutput();
        if ($errors) {
            return false;
            var_dump($errors); /** @todo */
        }
        return $proc->getOutput();
    }

    public function dumpDatabase(SshInfo $ssh, DatabaseInfo $db)
    {
        $scenario = new Scenario('Dump Database', $ssh);
        $driver = strtolower($db->getDriver());

        if (strpos($driver, 'mysql') !== false) {
            $setupCredentials = new SetupStep(
                'Setup MySQL Credentials',
                [
                    'mkdir -p ~/.mysql',
                    'chmod 0700 ~/.mysql',
                    'touch ~/.mysql/mysqldump.cnf',
                    "echo [mysqldump] > ~/.mysql/mysqldump.cnf",
                    "echo user={$db->getUser()} >> ~/.mysql/mysqldump.cnf",
                    "echo password={$db->getPass()} >> ~/.mysql/mysqldump.cnf",
                    'chmod 0400 ~/.mysql/mysqldump.cnf',
                ]);
            $teardownCredentials = new TeardownStep(
                'Teardown MySQL Credentials',
                [
                    'rm ~/.mysql/mysqldump.cnf',
                ],
                $setupCredentials
            );

            $chainDump = (new ScenarioStep('MySQL Dump', true))
                ->setCommands([
                    "mysqldump --defaults-extra-file=~/.mysql/mysqldump.cnf -h {$db->getHost()} {$db->getName()} > {$db->getTempFile()}",
                ]);
            $chainArchive = (new ScenarioStep('Archive', true))
                ->setCommands([
                    "tar cvjf {$db->getArchiveFile()} {$db->getTempFile()}",
                ]);

            $teardownSql = new TeardownStep(
                'Remove Remote SQL File', ["rm {$db->getTempFile()}"], $chainDump);
            $teardownArchive = new TeardownStep(
                'Remote Remote Archive File', ["rm {$db->getArchiveFile()}"], $chainArchive);

            $archiveFile = $db->getArchiveFile();
            $scpCommand = $ssh->getScpCommand($archiveFile, $archiveFile);
            // \var_dump($scpCommand); die;
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
                ->addSetupStep($setupCredentials)
                ->addTeardownStep($teardownCredentials)
                ->addChainStep($chainDump)
                ->addTeardownStep($teardownSql)
                ->addChainStep($chainArchive)
                ->addTeardownStep($teardownArchive)
                ->addChainStep($downloadArchive)
                ->addTeardownStep($teardownDownload);

            // var_dump($scenario);
            // echo $scenario->preview();die;

        } elseif (strpos($driver, 'pgsql') !== false) {

        } else {
            throw new \Exception('Invalid driver');
        }

        $scenario->run();
        // ->addSteps([
        //     (new ScenarioStep(''))
        //         // ->
        // ]);

        // static::runRemote($ssh, )
    }

    public function restoreDatabase()
    {

    }

    public function getDatabaseArchive(SshInfo $ssh)
    {

    }

    // public static function getCredentials(SshInfo $ssh): DatabaseInfo
    // {
    //     $cmd = "cd {$remoteWorkingDir}; grep .env -e 'DB'";
    //     $output = static::runRemote($ssh, $cmd);
    //     var_dump($output);
    // }
}
