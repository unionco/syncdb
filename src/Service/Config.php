<?php

namespace unionco\syncdb\Service;

use unionco\syncdb\Model\DatabaseInfo;

class Config
{
    public const COMMON = 'common';
    public const EXTENDS = 'extends';

    public const LOCAL_WORKING_DIR = 'localWorkingDir';
    public const REMOTE_WORKING_DIR = 'remoteWorkingDir';

    public const DEFAULT_CONFIG = [
        DatabaseInfo::IGNORE_TABLES => [],
        DatabaseInfo::DRIVER => 'mysql',
        DatabaseInfo::USER => '',
        DatabaseInfo::PASS => '',
        DatabaseInfo::HOST => '',
        DatabaseInfo::NAME => '',
        DatabaseInfo::ARGS => '',
    ];

    public static function parseConfig(array $config, string $environment = 'production') {
        // get the common parts
        $common = $config[self::COMMON] ?? [];
        $common = \array_merge(self::DEFAULT_CONFIG, $common);

        // Check to see if there are any global db overrides
        $dbOverrides = [];
        if (\key_exists(DatabaseInfo::OVERRIDE, $common)) {
            $dbOverrides = $common[DatabaseInfo::OVERRIDE];
        }
        // get the specified env
        $env = $config[$environment] ?? [];

        // If this config extends another, load that first
        if (\key_exists(self::EXTENDS, $env)) {
            $e = $env[self::EXTENDS];
            try {
                $common = \array_merge($common, $config[$e]);
            } catch (\Throwable $e) {
                throw $e;
            }
        }
        // Now check for more specific DB overrides for this
        // environment
        if (\key_exists(DatabaseInfo::OVERRIDE, $env)) {
            $dbOverrides = \array_merge($dbOverrides, $env[DatabaseInfo::OVERRIDE]);
        }

        $mergedConfig = \array_merge($common, $env);

        if (empty($dbOverrides)) {
            return $mergedConfig;
        }

        $mergedConfig[DatabaseInfo::OVERRIDE] = $dbOverrides;
        return $mergedConfig;
    }
}
