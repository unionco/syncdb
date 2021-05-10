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

    public static function parseConfig(array $config, string $environment = 'production') {
        // get the common parts
        $common = $config['common'] ?? [];
        $common = \array_merge(self::DEFAULT_CONFIG, $common);

        // get the specified env
        $env = $config[$environment] ?? [];

        // If this config extends another, load that first
        if (\key_exists('extends', $env)) {
            $e = $env['extends'];
            try {
                $common = \array_merge($common, $config[$e]);
            } catch (\Throwable $e) {
                throw $e;
            }
        }

        return \array_merge($common, $env);
    }
}
