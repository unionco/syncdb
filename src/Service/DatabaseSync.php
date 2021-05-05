<?php

namespace unionco\syncdb\Service;

// use unionco\syncdbFacade;
use Monolog\Logger;
use unionco\syncdb\Model\Step;
use unionco\syncdb\Model\SshInfo;
use Monolog\Handler\StreamHandler;
use unionco\syncdb\Model\Scenario;
use unionco\syncdb\Model\SetupStep;
use Symfony\Component\Process\Process;
use unionco\syncdb\Model\DatabaseInfo;
use unionco\syncdb\Model\ScenarioStep;
use unionco\syncdb\Model\TeardownStep;

// use Symfony\Component\Validator\Validator\ValidatorInterface;

class DatabaseSync
{
    // /** @var ValidatorInterface */
    // protected static $validator;
    private static $logger;

    public function __construct()
    {
        if (\class_exists('\monolog\Logger') && \class_exists('\monolog\Handler\StreamHandler')) {
            static::$logger = new Logger('syncdb');
            static::$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
        }
    }

    private static function info($msg)
    {
        if (static::$logger) {
            static::$logger->info($msg);
        }
    }
    public static function runRemote(SshInfo $ssh, Step $step)
    {
        static::info(__METHOD__ . ' start');
        $cmd = $step->getCommandString($ssh);
        $proc = Process::fromShellCommandline($cmd);

        try {
            $proc->mustRun();
        } catch (\Throwable$e) {
            throw $e;
        }
        $errors = $proc->getErrorOutput();
        if ($errors) {
            var_dump($errors); /** @todo */
        }
        return $proc->getOutput();
    }

    public static function runLocal(string $command)
    {

    }

    public function dumpDatabase(SshInfo $ssh, DatabaseInfo $db)
    {
        $scenario = new Scenario('Dump Database', $ssh);
        $driver = strtolower($db->getDriver());

        if (strpos($driver, 'mysql') !== false) {
            $setupCredentials = new SetupStep(
                'Setup MySQL Credentials',
                [
                    'mkdir ~/.mysql',
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
                    "mysqldump --defaults-extra-file=~/.mysql/mysqldump.cnf {$db->getName()} > {$db->getTempFile()}",
                ]);
            $chainArchive = (new ScenarioStep('Archive', true))
                ->setCommands([
                    "tar cvjf {$db->getArchiveFile()} {$db->getTempFile()}",
                ]);

            $teardownSql = new TeardownStep('Remove Remote SQL File', ["rm {$db->getTempFile()}"], $chainDump);
            $teardownArchive = new TeardownStep('Remote Remote Archive File', ["rm {$db->getArchiveFile()}"], $chainArchive);

            $downloadArchive = new ScenarioStep('Download Archive File', [$ssh->getScpCommand($db->getArchiveFile(), $db->getArchiveFile())], false);
            $teardownDownload = new TeardownStep('Remove Local Archive File', ["rm {$db->getArchiveFile()}"], $downloadArchive, false);

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
            echo $scenario->preview();die;

        } elseif (strpos($driver, 'pgsql') !== false) {

        } else {
            throw new \Exception('Invalid driver');
        }
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
