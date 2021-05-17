<?php

namespace unionco\syncdb\Service\Database;

use unionco\syncdb\Model\DatabaseInfo;
use unionco\syncdb\Model\Scenario;
use unionco\syncdb\Model\SshInfo;

interface DatabaseImplementation
{
    /**
     * @return Scenario
     */
    public static function dumpDatabase(Scenario $scenario, DatabaseInfo $db): Scenario;

    /**
     * @return Scenario
     */
    public static function importDatabase(Scenario $scenario, DatabaseInfo $db): Scenario;

    /**
     * @return string[]
     */
    public static function setupCredentialsCommands(DatabaseInfo $db, bool $dump = true);

    /**
     * @return string[]
     */
    public static function teardownCredentialsCommands();

    /** @return Scenario */
    public static function credentials(Scenario $scenario, DatabaseInfo $db, bool $remote): Scenario;

    /** @return Scenario */
    public static function dump(Scenario $scenario, DatabaseInfo $db): Scenario;

    /** @return Scenario */
    public static function archive(Scenario $scenario, DatabaseInfo $db): Scenario;

    /** @return Scenario */
    public static function download(Scenario $scenario, DatabaseInfo $db, SshInfo $ssh): Scenario;

    /** @return Scenario */
    public static function unarchive(Scenario $scenario, DatabaseInfo $db): Scenario;

    /** @return Scenario */
    public static function import(Scenario $scenario, DatabaseInfo $db): Scenario;
}
