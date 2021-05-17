<?php

namespace unionco\syncdb\Service\Database;

use unionco\syncdb\Model\Step;
use unionco\syncdb\Model\Scenario;
use unionco\syncdb\Model\SetupStep;
use unionco\syncdb\Model\DatabaseInfo;
use unionco\syncdb\Model\ChainStep;
use unionco\syncdb\Model\TeardownStep;
use unionco\syncdb\Service\Database\DatabaseImplementation;
use unionco\syncdb\Service\Database\AbstractDatabaseImplementation;

class Mysql extends AbstractDatabaseImplementation
{
    private const CREDENTIALS_PATH = '~/.mysql';
    private const CREDENTIALS_FILE = '~/.mysql/syncdb.cnf';

    private static function getClientCmd()
    {
        return '/usr/bin/mysql';
    }

    public static function credentials(Scenario $scenario, DatabaseInfo $db, bool $remote): Scenario
    {
        $remoteString = $remote ? 'Remote' : 'Local';
        $setup = (new SetupStep())
            ->setName("Setup {$remoteString} MySQL Credentials")
            ->setCommands(self::setupCredentialsCommands($db, $remote))
            ->setRemote($remote);

        $teardown = (new TeardownStep())
            ->setName("Teardown {$remoteString} MySQL Credentials")
            ->setCommands(self::teardownCredentialsCommands())
            ->setRelated($setup)
            ->setRemote($remote);

        return $scenario->addSetupStep($setup)
            ->addTeardownStep($teardown);
    }

    public static function dump(Scenario $scenario, DatabaseInfo $db): Scenario
    {
        $host = $db->getHost();
        $port = $db->getPort();
        $name = $db->getname();
        $remoteTempDump = $db->getTempFile(true, true);

        $credentialsFile = self::CREDENTIALS_FILE;

        $chain = (new ChainStep())
            ->setName('MySQL Dump')
            ->setRemote(true)
            ->setCommands([
                "mysqldump --defaults-extra-file={$credentialsFile} --no-tablespaces -h {$host} -P {$port} {$name} > {$remoteTempDump}",
            ]);

        $teardown = (new TeardownStep())
            ->setName('Remove Remote SQL File')
            ->setCommands(["rm {$remoteTempDump}"])
            ->setRemote(true)
            ->setRlated($chain);

        return $scenario->addChainStep($dump)
            ->addTeardownStep($teardown);
    }

    public static function import(Scenario $scenario, DatabaseInfo $db): Scenario
    {
        $host = $db->getHost();
        $port = $db->getPort();
        $name = $db->getName();
        $localDump = $db->getTempFile(true, false);

        $mysql = self::getClientCmd();

        $import = (new ChainStep())
            ->setName('Import Database')
            ->setRemote(false)
            ->setCommands([
                "{$mysql} --defaults-file=" . self::CREDENTIALS_FILE . " -h {$host} -P {$port} {$name} < {$localDump}",
            ]);

        return $scenario->addChainStep($import);
    }

    /** @inheritdoc */
    public static function setupCredentialsCommands(DatabaseInfo $db, bool $dump = true)
    {
        $user = $db->getUser();
        $pass = $db->getPass();

        $credentialsPath = self::CREDENTIALS_PATH;
        $credentialsFile = self::CREDENTIALS_FILE;
        $credsFileConditional = <<<EOFPHP
if [ -f {$credentialsFile} ]
then
  chmod 0600 {$credentialsFile}
fi
EOFPHP;
        return [
            "mkdir -p {$credentialsPath}",
            "chmod 0700 {$credentialsPath}",
            $credsFileConditional,
            "echo [" . ($dump ? 'mysqldump' : 'mysql') . "] > {$credentialsFile}",
            "echo user={$user} >> {$credentialsFile}",
            "echo password={$pass} >> {$credentialsFile}",
            "chmod 0400 {$credentialsFile}",
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
