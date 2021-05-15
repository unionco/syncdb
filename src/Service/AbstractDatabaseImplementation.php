<?php

namespace unionco\syncdb\Service;

use unionco\syncdb\Model\DatabaseInfo;
use unionco\syncdb\Model\Scenario;
use unionco\syncdb\Model\ScenarioStep;
use unionco\syncdb\Model\SshInfo;
use unionco\syncdb\Model\Step;
use unionco\syncdb\Model\TeardownStep;
use unionco\syncdb\Service\DatabaseImplementation;

abstract class AbstractDatabaseImplementation implements DatabaseImplementation
{
    public static function dumpDatabase(Scenario $scenario, DatabaseInfo $db): Scenario
    {
        $ssh = $scenario->getSshContext();
        // Setup a remote config file, which allows using mysqldump without passwords on the CLI/ENV
        $scenario = static::credentials($scenario, $db, true);

        // Dump the database to a temporary location
        $scenario = static::dump($scenario, $db);

        // Archive the remote SQL file using tar/bzip2
        $scenario = static::archive($scenario, $db);

        // Download the file using SCP
        $scenario = static::download($scenario, $db, $ssh);

        return $scenario;
    }

    public static function importDatabase(Scenario $scenario, DatabaseInfo $db): Scenario
    {
        // Setup a config file, used for mysql client
        $scenario = self::credentials($scenario, $db, false);

        // Unarchive the file that was downloaded
        $scenario = self::unarchive($scenario, $db);

        // Import the SQL file using mysql client
        $scnario = self::import($scenario, $db);

        return $scenario;
    }

    protected static function archive(Scenario $scenario, DatabaseInfo $db): Scenario
    {
        /** @var string /tmp/db.dump.bz2 */
        $remoteArchiveTarget = $db->getArchiveFile(true, true);

        $remoteTempDir = $db->getTempDir(true);
        $remoteTempDump = $db->getTempFile(false, true);

        $chain = (new ScenarioStep('Archive', true))
            ->setCommands([
                "tar cvjf {$remoteArchiveTarget} -C {$remoteTempDir} {$remoteTempDump}",
            ]);
        if (!$chain) {
            throw new \Exception('Invalid step');
        }
        $teardown = new TeardownStep(
            'Remote Remote Archive File', ["rm {$remoteArchiveTarget}"], $chain, true);

        return $scenario->addChainStep($chain)
            ->addTeardownStep($teardown);
    }

    protected static function download(Scenario $scenario, DatabaseInfo $db, SshInfo $ssh): Scenario
    {
        $remoteDownloadSource = $db->getArchiveFile(true, true);
        $localDownloadTarget = $db->getArchiveFile(true, false);

        $scpCommand = $ssh->getScpCommand($remoteDownloadSource, $localDownloadTarget);

        $downloadArchive = (new ScenarioStep(
            'Download Archive File',
            false))->setCommands([$scpCommand]);

        $teardownDownload = new TeardownStep(
            'Remove Local Archive File',
            ["rm {$localDownloadTarget}"],
            $downloadArchive,
            false
        );
        return $scenario->addChainStep($downloadArchive)
            ->addTeardownStep($teardownDownload);
    }

    protected static function unarchive(Scenario $scenario, DatabaseInfo $db): Scenario
    {
        $localTempDir = $db->getTempDir(false);

        $localArchive = $db->getArchiveFile(false, false);
        $localTempFile = $db->getTempFile(true, false);

        $localUnarchive = (new ScenarioStep('Unarchive Local SQL file', false))
            ->setCommands([
                "tar -C {$localTempDir} -xjf {$localArchive}",
            ]);
        $removeSqlFile = new TeardownStep(
            'Remove Local SQL File',
            ["rm {$localTempFile}"], $localUnarchive, false);
        return $scenario->addChainStep($localUnarchive)
            ->addTeardownStep($removeSqlFile);
    }

    protected static function dump(Scenario $scenario, DatabaseInfo $db): Scenario
    {
        return $scenario;
    }
}
