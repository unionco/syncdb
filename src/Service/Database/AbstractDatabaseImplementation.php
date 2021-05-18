<?php

namespace unionco\syncdb\Service\Database;

use unionco\syncdb\Model\DatabaseInfo;
use unionco\syncdb\Model\Scenario;
use unionco\syncdb\Model\ChainStep;
use unionco\syncdb\Model\SshInfo;
use unionco\syncdb\Model\Step;
use unionco\syncdb\Model\TeardownStep;
use unionco\syncdb\Service\Database\DatabaseImplementation;

abstract class AbstractDatabaseImplementation implements DatabaseImplementation
{
    /**
     * Add steps to the scenario to remotely dump the database
     */
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

    /**
     * Add steps to the scenario to import the database locally
     */
    public static function importDatabase(Scenario $scenario, DatabaseInfo $db): Scenario
    {
        // Setup a config file, used for mysql client
        $scenario = static::credentials($scenario, $db, false);

        // Unarchive the file that was downloaded
        $scenario = static::unarchive($scenario, $db);

        // Import the SQL file using mysql client
        $scnario = static::import($scenario, $db);

        return $scenario;
    }

    /**
     * Add steps to the scenario to archive and compress the database dump
     */
    public static function archive(Scenario $scenario, DatabaseInfo $db): Scenario
    {
        /** @var string /tmp/db.dump.bz2 */
        $remoteArchiveTarget = $db->getArchiveFile(true, true);

        $remoteTempDir = $db->getTempDir(true);
        $remoteTempDump = $db->getTempFile(false, true);

        $chain = (new ChainStep('Archive', true))
            ->setCommands([
                "tar -cvjf {$remoteArchiveTarget} -C {$remoteTempDir} {$remoteTempDump}",
            ]);
        if (!$chain) {
            throw new \Exception('Invalid step');
        }
        $teardown = new TeardownStep(
            'Remote Remote Archive File', ["rm {$remoteArchiveTarget}"], $chain, true);

        return $scenario->addChainStep($chain)
            ->addTeardownStep($teardown);
    }

    /**
     * Add steps to the scenario to download the archive
     */
    public static function download(Scenario $scenario, DatabaseInfo $db, SshInfo $ssh): Scenario
    {
        $remoteDownloadSource = $db->getArchiveFile(true, true);
        $localDownloadTarget = $db->getArchiveFile(true, false);

        $scpCommand = $ssh->getScpCommand($remoteDownloadSource, $localDownloadTarget);

        $downloadArchive = (new ChainStep())
            ->setName('Download Archive File')
            ->setRemote(false)
            ->setCommands([$scpCommand]);

        $teardownDownload = (new TeardownStep())
            ->setName('Remove Local Archive File')
            ->setCommands(["rm {$localDownloadTarget}"])
            ->setRelated($downloadArchive)
            ->setRemote(false);

        return $scenario->addChainStep($downloadArchive)
            ->addTeardownStep($teardownDownload);
    }

    /**
     * Add steps to the scenario to unarchive the downloaded file
     */
    public static function unarchive(Scenario $scenario, DatabaseInfo $db): Scenario
    {
        $localTempDir = $db->getTempDir(false);

        $localArchive = $db->getArchiveFile(true, false);
        $localTempFile = $db->getTempFile(true, false);

        $localUnarchive = (new ChainStep())
            ->setName('Unarchive Local SQL file')
            ->setRemote(false)
            ->setCommands([
                "tar -C {$localTempDir} -xjf {$localArchive}",
            ]);
        $removeSqlFile = (new TeardownStep())
            ->setName('Remove Local SQL File')
            ->setCommands(["rm {$localTempFile}"])
            ->setRelated($localUnarchive)
            ->setRemote(false);

        return $scenario->addChainStep($localUnarchive)
            ->addTeardownStep($removeSqlFile);
    }
}
