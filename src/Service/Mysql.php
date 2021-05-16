<?php

namespace unionco\syncdb\Service;

use unionco\syncdb\Model\Step;
use unionco\syncdb\Model\Scenario;
use unionco\syncdb\Model\SetupStep;
use unionco\syncdb\Model\DatabaseInfo;
use unionco\syncdb\Model\ScenarioStep;
use unionco\syncdb\Model\TeardownStep;
use unionco\syncdb\Service\DatabaseImplementation;
use unionco\syncdb\Service\AbstractDatabaseImplementation;

class Mysql extends AbstractDatabaseImplementation
{
    private const CREDENTIALS_PATH = '~/.mysql';
    private const CREDENTIALS_FILE = '~/.mysql/syncdb.cnf';

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
