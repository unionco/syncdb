<?php

namespace unionco\syncdb\Service;

use unionco\syncdb\Model\Scenario;
use unionco\syncdb\Model\DatabaseInfo;

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
}
