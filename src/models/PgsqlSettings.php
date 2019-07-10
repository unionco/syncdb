<?php

namespace unionco\syncdb\models;

use unionco\syncdb\models\Settings;

class PgsqlSettings extends Settings
{
    public static $envVarClientPath = 'PSQL_CLIENT_PATH';
    public static $envVarDumpClientPath = 'PSQL_DUMP_PATH';
    public static $defaultClientPaths = [
        '/usr/bin/psql',
        '/usr/local/bin/psql',
    ];
    public static $defaultDumpClientPaths = [
        '/usr/bin/pg_dump',
        '/usr/local/bin/pg_dump',
    ];
}
