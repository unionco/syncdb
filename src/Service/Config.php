<?php

namespace unionco\syncdb\Service;

class Config
{
    public const DEFAULT_CONFIG = [
        'readDbConfigFromDotEnv' => true,
        'ignoreTables' => [],
        'driver' => 'mysql',
        'dbUser' => '',
        'dbPass' => '',
        'dbHost' => '',
        'dbName' => '',
        'dbArgs' => '',
    ];

    public static function parseConfig(string $filename, string $environment = 'production')
    {
        /** @var array */
        $opts = json_decode(\file_get_contents($filename), true);

        // get the common parts
        $common = $opts['common'] ?? [];
        $common = \array_merge(self::DEFAULT_CONFIG, $common);

        // get the specified env
        $env = $opts[$environment] ?? [];

        return \array_merge($common, $env);
    }

    // public static function findConfigFile()
    // {
    //     $paths = [
    //         ''
    //     ]
    // }
}
