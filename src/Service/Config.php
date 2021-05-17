<?php

namespace unionco\syncdb\Service;

use unionco\syncdb\Model\DatabaseInfo;

class Config
{
    public const C_COMMON = 'common';
    public const C_EXTENDS = 'extends';
    public const C_LOCAL = 'local';
    public const C_DOCKER = 'docker';
    public const C_DOCKER_DB = 'dockerDatabase';

    public const LOCAL_WORKING_DIR = 'localWorkingDir';
    public const REMOTE_WORKING_DIR = 'remoteWorkingDir';

    public const DEFAULT_CONFIG = [
        DatabaseInfo::IGNORE_TABLES => [],
        DatabaseInfo::DRIVER => DatabaseInfo::MYSQL,
        DatabaseInfo::USER => '',
        DatabaseInfo::PASS => '',
        DatabaseInfo::HOST => '',
        DatabaseInfo::NAME => '',
        DatabaseInfo::ARGS => '',
    ];

    /**
     * Parse config, inheritence, database, etc for the given env handle
     * @param array $config
     * @param string $environmentHandle
     * @return array{local:array,remote:array}
     */
    public static function parseConfig(array $config, string $environmentHandle = 'production')
    {
        // get the common parts
        $common = $config[self::C_COMMON] ?? [];
        $common = \array_merge(self::DEFAULT_CONFIG, $common);

        // Check to see if there are any global db overrides
        $dbOverrides = [];
        if (\key_exists(DatabaseInfo::OVERRIDE, $common)) {
            $dbOverrides = $common[DatabaseInfo::OVERRIDE];
        }
        $remoteEnvironment = self::handleInheritance($config, $environmentHandle);
        $remoteEnvironment = self::parseDockerConfig($remoteEnvironment);

        $localEnvironment = self::handleInheritance($config, 'local');
        $localEnvironment = self::parseDockerConfig($localEnvironment);

        return [
            'local' => $localEnvironment,
            'remote' => $remoteEnvironment,
        ];
    }

    /**
     * Handle inheritence for the given environment
     * @param array $config
     * @param string $environmentHandle
     * @return array
     */
    protected static function handleInheritance(array $config, string $environmentHandle): array
    {
        // Get the common base config for all environments
        $commonConfig = \key_exists(self::C_COMMON, $config) ? $config[self::C_COMMON] : [];

        // Get the config for the requested environment
        $environmentConfig = \key_exists($environmentHandle, $config) ? $config[$environmentHandle] : [];

        // If the requested environment exists and has an 'extends' attribute,
        // load that configuration
        $extendsConfig = \key_exists(self::C_EXTENDS, $environmentConfig) ? $environmentConfig[self::C_EXTENDS] : [];
        if (!empty($extendsConfig)) {
            // Merge the parent config into the common base config
            $extendsConfig = \array_merge($commonConfig, $extendsConfig);
            // Merge the requested config into the parent config
            $environmentConfig = \array_merge($extendsConfig, $environmentConfig);
            return $environmentConfig;
        }

        // The 'extends' parent config is empty, so just merge with the common base
        $environmentConfig = \array_merge($commonConfig, $environmentConfig);
        return $environmentConfig;
    }

    /**
     * @param array $environmentConfig
     * @return array
     */
    protected static function parseDockerConfig(array $environmentConfig)
    {
        $defaultDockerConfig = [
            'host' => 'localhost',
            'port' => 3306,
        ];

        if (!\key_exists(self::C_DOCKER, $environmentConfig) || !$environmentConfig[self::C_DOCKER] === true) {
            $environmentConfig[self::C_DOCKER] = false;
            $environmentConfig[self::C_DOCKER_DB] = [];
            return $environmentConfig;
        }
        $environmentDockerDbConfig = $environmentConfig[self::C_DOCKER_DB] ?? [];
        $dockerDbConfig = \array_merge($defaultDockerConfig, $environmentDockerDbConfig);

        $environmentConfig[self::C_DOCKER_DB] = $dockerDbConfig;
        return $environmentConfig;
    }
}
