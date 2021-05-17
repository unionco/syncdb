<?php

namespace unionco\syncdb\Service\Database;

use unionco\syncdb\Model\DatabaseInfo;
use unionco\syncdb\Model\Scenario;
use unionco\syncdb\Model\ChainStep;
use unionco\syncdb\Model\SetupStep;
use unionco\syncdb\Model\TeardownStep;
use unionco\syncdb\Service\Database\AbstractDatabaseImplementation;

class Postgres extends AbstractDatabaseImplementation
{
    private const CREDENTIALS_FILE = '~/.pgpass';
    private const CREDENTIALS_FILE_BACKUP = '~/.pgpass.bak';

    /** @inheritdoc */
    public static function credentials(Scenario $scenario, DatabaseInfo $db, bool $remote): Scenario
    {
        $remoteString = $remote ? 'Remote' : 'Local';
        $setup = new SetupStep(
            "Setup {$remoteString} Postgres Credentials",
            self::setupCredentialsCommands($db),
            $remote
        );
        $teardown = new TeardownStep(
            "Teardown {$remoteString} Postgres Credentials",
            self::teardownCredentialsCommands(),
            $setup,
            $remote
        );
        return $scenario->addSetupStep($setup)
            ->addTeardownStep($teardown);
    }

    /**
     * @inheritdoc
     */
    public static function dump(Scenario $scenario, DatabaseInfo $db): Scenario
    {
        $name = $db->getName();
        $host = $db->getHost();
        $port = $db->getPort();
        $user = $db->getUser();

        $remoteDumpTarget = $db->getTempFile(true, true);
        $dump = (new ChainStep('Postgres Dump', true))
            ->setCommands([
                "pg_dump -Fc -d {$name} -U {$user} -h {$host} -p {$port} > {$remoteDumpTarget}",
            ]);
        $teardown = new TeardownStep(
            'Remove Remote SQL File', ["rm {$remoteDumpTarget}"], $dump);

        return $scenario->addChainStep($dump)
            ->addTeardownStep($teardown);
    }

    /**
     * @inheritdoc
     */
    public static function import(Scenario $scenario, DatabaseInfo $db): Scenario
    {
        $name = $db->getName();
        $host = $db->getHost();
        $port = $db->getPort();
        $user = $db->getUser();

        $pgsqlCreds = "-U {$user} -h {$host} -p {$port}";

        $localDump = $db->getTempFile(true, false);
        $import = (new ChainStep('Import Database', false))
            ->setCommands([
                "dropdb --force {$pgsqlCreds} {$name}",
                "createdb {$pgsqlCreds} {$name}",
                "pg_restore --clean --if-exists --no-password -e -d {$name} {$pgsqlCreds} {$localDump}",
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
        $credentialsFile = self::CREDENTIALS_FILE;
        $credentialsBackup = self::CREDENTIALS_FILE_BACKUP;

        $credsFileConditional = <<<EOFPHP
if [ -f {$credentialsFile} ]
then
  chmod 0700 {$credentialsFile}
  mv {$credentialsFile} {$credentialsBackup}
fi
EOFPHP;

        return [
            $credsFileConditional,
            "echo {$connectionString} > {$credentialsFile}",
            "chmod 0600 {$credentialsFile}",
        ];
    }

    /** @inheritdoc */
    public static function teardownCredentialsCommands()
    {
        $credentialsFile = self::CREDENTIALS_FILE;
        $credentialsBackup = self::CREDENTIALS_FILE_BACKUP;
        $credsFileConditional = <<<EOFPHP
if [ -f {$credentialsBackup} ]
then
  mv {$credentialsBackup} {$credentialsFile}
  chmod 0600 {$credentialsFile}
fi
EOFPHP;
        return [
            $credsFileConditional,
        ];
    }
}
