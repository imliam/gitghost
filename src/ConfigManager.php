<?php

namespace GitGhost;

class ConfigManager
{
    private const CONFIG_FILE = '.gitghost.json';
    private static array $config = [];
    private static ?string $configPath = null;

    public static function load(): void
    {
        if (!self::$configPath) {
            $homeDir = getenv('HOME') ?: getenv('USERPROFILE');
            self::$configPath = $homeDir . DIRECTORY_SEPARATOR . self::CONFIG_FILE;
        }

        if (file_exists(self::$configPath)) {
            self::$config = json_decode(file_get_contents(self::$configPath), true) ?? [];
        } else {
            self::$config = [];
        }
    }

    public static function get(?string $key = null, $default = null)
    {
        if (empty(self::$config)) {
            self::load();
        }

        if ($key === null) {
            return self::$config;
        }

        return self::$config[$key] ?? $default;
    }

    public static function save(array $newConfig): void
    {
        if (empty(self::$config)) {
            self::load();
        }

        self::$config = array_merge(self::$config, $newConfig);

        file_put_contents(self::$configPath, json_encode(self::$config, JSON_PRETTY_PRINT));
    }

    public static function getDefaultDummyRepoPath(): string
    {
        $homeDir = getenv('HOME') ?: getenv('USERPROFILE');
        return $homeDir . DIRECTORY_SEPARATOR . '.gitghost';
    }

    public static function getConfigPath(): string
    {
        if (!self::$configPath) {
            $homeDir = getenv('HOME') ?: getenv('USERPROFILE');
            self::$configPath = $homeDir . DIRECTORY_SEPARATOR . self::CONFIG_FILE;
        }

        return self::$configPath;
    }

    public static function getSyncedRepos(): array
    {
        return self::get('synced_repos', []);
    }
}
