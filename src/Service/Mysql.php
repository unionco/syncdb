<?php

namespace unionco\syncdb\Service;

use unionco\syncdb\Model\DatabaseInfo;
use unionco\syncdb\Model\Scenario;
use unionco\syncdb\Model\ScenarioStep;
use unionco\syncdb\Model\SetupStep;
use unionco\syncdb\Model\Step;
use unionco\syncdb\Model\TeardownStep;
use unionco\syncdb\Service\DatabaseImplementation;

class Mysql implements DatabaseImplementation
{
    private const CREDENTIALS_PATH = '$HOME/.mysql';
    private const CREDENTIALS_FILE = '$HOME/.mysql/syncdb.cnf';

    public static function credentials(Scenario $scenario, DatabaseInfo $db, bool $remote): Scenario
    {
        $remoteString = $remote ? 'Remote' : 'Local';
        $setup = new SetupStep(
            "Setup {$remoteString} MySQL Credentials",
            self::setupCredentialsCommands($db, $remote),
            $remote
        );
        $teardown = new TeardownStep(
            "Teardown {$remoteString} MySQL Credentials",
            self::teardownCredentialsCommands(),
            $setup,
            $remote
        );
        return $scenario->addSetupStep($setup)
            ->addTeardownStep($teardown);
    }

    public static function dump(Scenario $scenario, DatabaseInfo $db): Scenario
    {
        $host = $db->getHost();
        $port = $db->getPort();
        $name = $db->getname();
        $remoteTempDump = $db->getTempFile(true, true);

        $dump = (new ScenarioStep('MySQL Dump', true))
            ->setCommands([
                "mysqldump --defaults-extra-file=" . self::CREDENTIALS_FILE . " -h {$host} -P {$port} {$name} > {$remoteTempDump}",
            ]);
        $teardown = new TeardownStep(
            'Remove Remote SQL File', ["rm {$db->getTempFile()}"], $dump);

        return $scenario->addChainStep($dump)
            ->addTeardownStep($teardown);
    }

    public static function import(Scenario $scenario, DatabaseInfo $db): Scenario
    {
        $host = $db->getHost();
        $port = $db->getPort();
        $name = $db->getName();
        $localDump = $db->getTempFile(true, false);

        $import = (new ScenarioStep('Import Database', false))
            ->setCommands([
                "mysql --defaults-file=" . self::CREDENTIALS_FILE . " -h {$host} -P {$port} {$name} < {$localDump}",
            ]);
        return $scenario->addChainStep($import);
    }

    /** @inheritdoc */
    public static function setupCredentialsCommands(DatabaseInfo $db, bool $dump = true)
    {
        $user = $db->getUser();
        $pass = $db->getPass();

        return [
            "mkdir -p " . self::CREDENTIALS_PATH . "",
            "chmod 0700 " . self::CREDENTIALS_PATH . "",
            "if test -f " . self::CREDENTIALS_FILE . "; then chmod 0600 " . self::CREDENTIALS_FILE . "; else touch " . self::CREDENTIALS_FILE . "; fi",
            "echo [" . ($dump ? 'mysqldump' : 'mysql') . "] > " . self::CREDENTIALS_FILE . "",
            "echo user={$user} >> " . self::CREDENTIALS_FILE . "",
            "echo password={$pass} >> " . self::CREDENTIALS_FILE . "",
            "chmod 0400 " . self::CREDENTIALS_FILE . "",
        ];
    }
    /** @inheritdoc */
    public static function teardownCredentialsCommands()
    {
        return [
            "rm " . self::CREDENTIALS_FILE . "",
        ];
    }
}
