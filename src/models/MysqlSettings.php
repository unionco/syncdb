<?php

namespace unionco\syncdb\models;

use unionco\syncdb\models\Settings;

class MysqlSettings extends Settings
{
    public static $envVarClientPath = 'MYSQL_CLIENT_PATH';
    public static $envVarDumpClientPath = 'MYSQL_DUMP_PATH';
    public static $defaultClientPaths = [
        '/usr/bin/mysql',
        '/usr/local/bin/mysql'
    ];
    public static $defaultDumpClientPaths = [
        '/usr/bin/mysqldump',
        '/usr/local/bin/mysqldump',
    ];
}
