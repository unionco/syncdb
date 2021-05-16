<?php

namespace unionco\syncdb\Service;

use unionco\syncdb\Model\Step;
use unionco\syncdb\Model\SshInfo;
use unionco\syncdb\Model\Scenario;
use unionco\syncdb\Model\SetupStep;
use unionco\syncdb\Model\DatabaseInfo;
use unionco\syncdb\Model\ScenarioStep;
use unionco\syncdb\Model\TeardownStep;
use unionco\syncdb\Service\AbstractDatabaseImplementation;

class Postgres extends AbstractDatabaseImplementation
{
    private const CREDENTIALS_FILE = '$HOME/.pgpass';
    private const CREDENTIALS_FILE_BACKUP = '$HOME/.pgpass.bak';

         /** @inheritdoc */
    public static function credentials(Scenario $scenario, DatabaseInfo $db, bool $remote): Scenario
    {
        $remoteString = $remote ? 'Remote' : 'Local';
        $setup = new SetupStep(
            "Setup {$remoteString} Postgres Credentials",
            self::setupCredentialsCommands($db, $remote)
        );
        $teardown = new TeardownStep(
            "Teardown {$remoteString} Postgres Credentials",
            self::teardownCredentialsCommands(),
            $setup
        );
        return $scenario->addSetupStep($setup)
            ->addTeardownStep($teardown);
    }

    public static function dump(Scenario $scenario, DatabaseInfo $db): Scenario
    {
        $name = $db->getName();
        $host = $db->getHost();
        $port = $db->getPort();
        $user = $db->getUser();

        $remoteDumpTarget = $db->getTempFile(true, true);
        $dump = (new ScenarioStep('Postgres Dump', true))
            ->setCommands([
                "pg_dump -Fc -d {$name} -U {$user} -h {$host} -p {$port} > {$remoteDumpTarget}",
            ]);
        $teardown = new TeardownStep(
            'Remove Remote SQL File', ["rm {$remoteDumpTarget}"], $dump);

        return $scenario->addChainStep($dump)
            ->addTeardownStep($teardown);
    }

    public static function import(Scenario $scenario, DatabaseInfo $db): Scenario
    {
        $name = $db->getName();
        $host = $db->getHost();
        $port = $db->getPort();
        $user = $db->getUser();

        $localDump = $db->getTempFile(true, false);
        $import = (new ScenarioStep('Import Database', false))
            ->setCommands([
                "pg_restore -d {$name} -U {$user} -h {$host} -p {$port} {$localDump}",
            ]);
        return $scenario->addChainStep($import);
    }

    /**
     * @inheritdoc
     */
    public static function setupCredentialsCommands(DatabaseInfo $db, bool $dump = true)
    {
        $pass = $db->getPass();

        $connectionString = "*:*:*:*:{$pass}";
        return [
            "if test -f " . self::CREDENTIALS_FILE . "; then chmod 0700 " . self::CREDENTIALS_FILE . "; mv " . self::CREDENTIALS_FILE . " " . self::CREDENTIALS_FILE_BACKUP . "; else touch " . self::CREDENTIALS_FILE . "; fi",
            "echo {$connectionString} > " . self::CREDENTIALS_FILE . "",
            "chmod 0600 " . self::CREDENTIALS_FILE . "",
        ];
    }


    /** @inheritdoc */
    public static function teardownCredentialsCommands()
    {
        return [
            "if test -f " . self::CREDENTIALS_FILE_BACKUP . "; mv " . self::CREDENTIALS_FILE_BACKUP . " " . self::CREDENTIALS_FILE . "; chmod 0600 " . self::CREDENTIALS_FILE . "; fi;",
        ];
    }
}
