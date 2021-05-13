<?php

namespace unionco\syncdb\Service;

use unionco\syncdb\Model\Scenario;
use unionco\syncdb\Model\SetupStep;
use unionco\syncdb\Model\DatabaseInfo;
use unionco\syncdb\Model\ScenarioStep;
use unionco\syncdb\Model\TeardownStep;

class Postgres implements DatabaseImplementation
{
    private const CREDENTIALS_FILE = '~/.pgpass';
    private const CREDENTIALS_FILE_BACKUP = '~/.pgpass.bak';

    public static function dumpDatabase(Scenario $scenario, DatabaseInfo $db): Scenario
    {
        $ssh = $scenario->getSshContext();
        // Setup a remote config file, which allows using mysqldump without passwords on the CLI/ENV
        $setupRemotePostgresCredentials = new SetupStep(
            'Setup Remote Postgres Credentials',
            self::setupCredentialsCommands($db, true)
        );
        $teardownRemoteCredentials = new TeardownStep(
            'Teardown Remote Postgres Credentials',
            self::teardownCredentialsCommands(),
            $setupRemotePostgresCredentials
        );

        $scenario
            ->addSetupStep($setupRemotePostgresCredentials)
            ->addTeardownStep($teardownRemoteCredentials);

        // Dump the database to a temporary location
        $chainDump = (new ScenarioStep('Postgres Dump', true))
            ->setCommands([
                "pg_dump -Fc {$db->getName()} > {$db->getTempFile()}",
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
        return $scenario;
    }

    public static function importDatabase(Scenario $scenario, DatabaseInfo $db): Scenario
    {
        // Setup a config file, used for mysql client
        $setupLocalPostgresCredentials = new SetupStep(
            'Setup Local Postgres Credentials',
            self::setupCredentialsCommands($db, false),
            false
        );
        $teardownLocalCredentials = new TeardownStep(
            'Teardown Local Postgres Credentials',
            self::teardownCredentialsCommands(),
            $setupLocalPostgresCredentials,
            false
        );

        $scenario->addSetupStep($setupLocalPostgresCredentials)
            ->addTeardownStep($teardownLocalCredentials);

        // Unarchive the file that was downloaded
        $localUnarchive = (new ScenarioStep('Unarchive Local SQL file', false))
            ->setCommands([
                "cd {$db->getTempDir(false)}; tar xjf {$db->getArchiveFile(false, false)}",
            ]);
        $removeSqlFile = new TeardownStep('Remove Local SQL File', ["rm {$db->getTempFile(true, false)}"], $localUnarchive, false);

        $scenario->addChainStep($localUnarchive)
            ->addTeardownStep($removeSqlFile);

        // Import the SQL file using mysql client
        $import = (new ScenarioStep('Import Database', false))
            ->setCommands([
                "pg_restore -d {$db->getName()} {$db->getTempFile(true, false)}",
            ]);
        $scenario->addChainStep($import);

        return $scenario;
    }

    /**
     * @inheritdoc
     */
    public static function setupCredentialsCommands(DatabaseInfo $db, bool $dump = true)
    {
        $host = $db->getHost();
        $port = $db->getPort();
        $name = $db->getName();
        $user = $db->getUser();
        $pass = $db->getPass();

        $connectionString = "{$host}:{$port}:{$name}:{$user}:{$pass}";
        return [
            "/bin/sh -c \"if test -f " . self::CREDENTIALS_FILE . "; then chmod 0700 " . self::CREDENTIALS_FILE . "; mv " . self::CREDENTIALS_FILE . " " . self::CREDENTIALS_FILE_BACKUP . "; else touch " . self::CREDENTIALS_FILE . "; fi\"",
            "echo {$connectionString} > " . self::CREDENTIALS_FILE . "",
            "chmod 0600 " . self::CREDENTIALS_FILE . "",
        ];
    }

    /** @inheritdoc */
    public static function teardownCredentialsCommands()
    {
        return [
            "if test -f " . self::CREDENTIALS_FILE_BACKUP . "; mv " . self::CREDENTIALS_FILE_BACKUP . " " . self::CREDENTIALS_FILE . "; chmod 0600 " . self::CREDENTIALS_FILE . "; fi;"
        ];
    }
}
