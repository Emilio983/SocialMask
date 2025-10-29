<?php
declare(strict_types=1);

namespace TheSocialMask\Config;

use RuntimeException;

/**
 * Lightweight environment loader/manager for TheSocialMask.
 *
 * Loads key/value pairs from the project .env file and exposes helpers for
 * retrieving typed values. Ensures the file is only parsed once per request.
 */
final class Env
{
    private static bool $loaded = false;

    /** @var array<string, string> */
    private static array $cache = [];

    /**
     * Load environment variables from .env if it exists.
     *
     * @param string|null $path Optional absolute path to the .env file.
     */
    public static function load(?string $path = null): void
    {
        if (self::$loaded) {
            return;
        }

        $path = $path ?? dirname(__DIR__) . '/.env';

        if (is_file($path) && is_readable($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES);

            if ($lines !== false) {
                foreach ($lines as $line) {
                    self::processLine($line);
                }
            }
        }

        self::$loaded = true;
    }

    /**
     * Get an environment value. Returns default when not found.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        self::load();

        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        $value = getenv($key);

        if ($value === false) {
            return $default;
        }

        self::$cache[$key] = $value;

        return $value;
    }

    /**
     * Get a required environment variable. Throws when missing/empty.
     *
     * @throws RuntimeException
     */
    public static function require(string $key): string
    {
        $value = self::get($key);

        if ($value === null || $value === '') {
            throw new RuntimeException("Missing required environment variable: {$key}");
        }

        return $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        if ($value === null) {
            return $default;
        }

        $value = strtolower(trim($value));

        return in_array($value, ['1', 'true', 'on', 'yes'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);

        if (!is_numeric($value)) {
            return $default;
        }

        return (int) $value;
    }

    public static function float(string $key, float $default = 0.0): float
    {
        $value = self::get($key);

        if (!is_numeric($value)) {
            return $default;
        }

        return (float) $value;
    }

    /**
     * Ensure a list of keys exists.
     *
     * @param string[] $keys
     */
    public static function requireMany(array $keys): void
    {
        foreach ($keys as $key) {
            self::require($key);
        }
    }

    private static function processLine(string $line): void
    {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            return;
        }

        if (str_starts_with($line, 'export ')) {
            $line = substr($line, 7);
        }

        if (!str_contains($line, '=')) {
            return;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = self::sanitizeValue($value);

        if ($name === '') {
            return;
        }

        self::$cache[$name] = $value;
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
        putenv("{$name}={$value}");
    }

    private static function sanitizeValue(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        // Remove inline comments when value is not quoted.
        if (!self::isQuoted($value) && str_contains($value, '#')) {
            [$value] = explode('#', $value, 2);
            $value = trim($value);
        }

        if (self::isQuoted($value)) {
            $quote = $value[0];
            $value = substr($value, 1, -1);

            if ($quote === '"') {
                $value = str_replace(['\n', '\r'], ["\n", "\r"], $value);
            }
        }

        return $value;
    }

    private static function isQuoted(string $value): bool
    {
        return strlen($value) >= 2 &&
            (($value[0] === '"' && str_ends_with($value, '"')) ||
             ($value[0] === "'" && str_ends_with($value, "'")));
    }

    /**
     * Get SPHE token configuration
     *
     * @return array{address: string, symbol: string, decimals: int}
     */
    public static function spheToken(): array
    {
        return [
            'address' => self::require('SPHE_CONTRACT_ADDRESS'),
            'symbol' => self::get('MY_TOKEN_SYMBOL', 'SPHE'),
            'decimals' => self::int('MY_TOKEN_DECIMALS', 18),
        ];
    }

    /**
     * Get treasury configuration
     *
     * @return array{address: string, feePercentage: float, backup: string|null}
     */
    public static function treasury(): array
    {
        return [
            'address' => self::require('TREASURY_WALLET_ADDRESS'),
            'feePercentage' => self::float('PLATFORM_FEE_PERCENTAGE', 2.5),
            'backup' => self::get('BACKUP_TREASURY_WALLET'),
        ];
    }

    /**
     * Get IPFS configuration
     *
     * @return array{provider: string, apiKey: string, apiSecret: string, gateway: string, enabled: bool}
     */
    public static function ipfs(): array
    {
        return [
            'provider' => self::get('IPFS_PROVIDER', 'pinata'),
            'apiKey' => self::get('IPFS_API_KEY', ''),
            'apiSecret' => self::get('IPFS_API_SECRET', ''),
            'gateway' => self::get('IPFS_GATEWAY_URL', 'https://gateway.pinata.cloud'),
            'enabled' => self::bool('IPFS_ENABLED', false),
        ];
    }

    /**
     * Get Gun.js configuration
     *
     * @return array{relayUrl: string, enabled: bool}
     */
    public static function gunjs(): array
    {
        return [
            'relayUrl' => self::get('GUNJS_RELAY_URL', ''),
            'enabled' => self::bool('GUNJS_RELAY_ENABLED', false),
        ];
    }

    /**
     * Get donations general configuration
     *
     * @return array{enabled: bool, feePercentage: float, contractAddress: string|null, allowAnonymous: bool, trackInDb: bool, trackInGunjs: bool, leaderboardEnabled: bool, activeToken: string, activeBlockchain: string}
     */
    public static function donations(): array
    {
        return [
            'enabled' => self::bool('DONATIONS_ENABLED', true),
            'feePercentage' => self::float('DONATIONS_FEE_PERCENTAGE', 2.5),
            'contractAddress' => self::get('DONATION_CONTRACT_ADDRESS'),
            'allowAnonymous' => self::bool('DONATIONS_ALLOW_ANONYMOUS', true),
            'trackInDb' => self::bool('DONATIONS_TRACK_IN_DB', true),
            'trackInGunjs' => self::bool('DONATIONS_TRACK_IN_GUNJS', true),
            'leaderboardEnabled' => self::bool('DONATIONS_LEADERBOARD_ENABLED', true),
            'activeToken' => self::get('DONATIONS_ACTIVE_TOKEN', 'sphe'),
            'activeBlockchain' => self::get('DONATIONS_ACTIVE_BLOCKCHAIN', 'polygon'),
        ];
    }

    /**
     * Get active token configuration based on DONATIONS_ACTIVE_TOKEN
     *
     * @return array{address: string, symbol: string, decimals: int, name: string, minDonation: float, blockchain: string, chainId: int, rpcUrl: string|null, explorerUrl: string|null}
     */
    public static function activeToken(): array
    {
        $activeToken = self::get('DONATIONS_ACTIVE_TOKEN', 'sphe');
        return self::tokenConfig($activeToken);
    }

    /**
     * Get specific token configuration
     *
     * @param string $tokenName Token name: 'sphe', 'polygon', 'custom'
     * @return array{address: string, symbol: string, decimals: int, name: string, minDonation: float, blockchain: string, chainId: int, rpcUrl: string|null, explorerUrl: string|null}
     */
    public static function tokenConfig(string $tokenName): array
    {
        $prefix = strtoupper($tokenName);
        
        return [
            'address' => self::get("{$prefix}_TOKEN_ADDRESS", ''),
            'symbol' => self::get("{$prefix}_TOKEN_SYMBOL", 'UNKNOWN'),
            'decimals' => self::int("{$prefix}_TOKEN_DECIMALS", 18),
            'name' => self::get("{$prefix}_TOKEN_NAME", 'Unknown Token'),
            'minDonation' => self::float("{$prefix}_MIN_DONATION", 1.0),
            'blockchain' => self::get("{$prefix}_BLOCKCHAIN", 'polygon'),
            'chainId' => self::int("{$prefix}_CHAIN_ID", 137),
            'rpcUrl' => self::get("{$prefix}_RPC_URL"),
            'explorerUrl' => self::get("{$prefix}_EXPLORER_URL"),
        ];
    }

    /**
     * Get all supported tokens list
     *
     * @return array<string>
     */
    public static function supportedTokens(): array
    {
        return ['sphe', 'polygon', 'custom'];
    }

    /**
     * Check if a specific token is configured
     *
     * @param string $tokenName Token name to check
     * @return bool
     */
    public static function isTokenConfigured(string $tokenName): bool
    {
        $prefix = strtoupper($tokenName);
        $address = self::get("{$prefix}_TOKEN_ADDRESS", '');
        return !empty($address);
    }

    /**
     * Get pay-per-view configuration
     *
     * @return array{enabled: bool, ratePer1000: float, minPayout: float, schedule: string, contractAddress: string|null, bonuses: array}
     */
    public static function payPerView(): array
    {
        return [
            'enabled' => self::bool('PPV_ENABLED', true),
            'ratePer1000' => self::float('PPV_RATE_PER_1000_VIEWS', 10.0),
            'minPayout' => self::float('PPV_MIN_PAYOUT', 100.0),
            'schedule' => self::get('PPV_PAYOUT_SCHEDULE', 'weekly'),
            'contractAddress' => self::get('PPV_CONTRACT_ADDRESS'),
            'bonuses' => [
                '10k' => self::float('PPV_BONUS_10K_VIEWS', 20.0),
                '100k' => self::float('PPV_BONUS_100K_VIEWS', 50.0),
                '1m' => self::float('PPV_BONUS_1M_VIEWS', 100.0),
            ],
        ];
    }

    /**
     * Get journalist system configuration
     *
     * @return array{enabled: bool, minReputation: int, bonusPercentage: float, allowAnonymous: bool, contractAddress: string|null, autoApproveThreshold: int, verificationRequired: bool}
     */
    public static function journalist(): array
    {
        return [
            'enabled' => self::bool('JOURNALIST_SYSTEM_ENABLED', true),
            'minReputation' => self::int('JOURNALIST_MIN_REPUTATION', 100),
            'bonusPercentage' => self::float('JOURNALIST_PAYOUT_BONUS_PERCENTAGE', 50.0),
            'allowAnonymous' => self::bool('JOURNALIST_ANONYMOUS_ALLOWED', true),
            'contractAddress' => self::get('JOURNALIST_CONTRACT_ADDRESS'),
            'autoApproveThreshold' => self::int('JOURNALIST_AUTO_APPROVE_THRESHOLD', 1000),
            'verificationRequired' => self::bool('JOURNALIST_VERIFICATION_REQUIRED', false),
        ];
    }

    /**
     * Get encryption & privacy configuration
     *
     * @return array{e2eEnabled: bool, signalProtocol: bool, maxSizeMb: int, anonymousMode: bool, zkpEnabled: bool, polygonIdEnabled: bool, torFriendly: bool, retentionDays: int}
     */
    public static function privacy(): array
    {
        return [
            'e2eEnabled' => self::bool('E2E_ENCRYPTION_ENABLED', true),
            'signalProtocol' => self::bool('SIGNAL_PROTOCOL_ENABLED', false),
            'maxSizeMb' => self::int('MESSAGE_MAX_SIZE_MB', 5),
            'anonymousMode' => self::bool('ANONYMOUS_MODE_ENABLED', true),
            'zkpEnabled' => self::bool('ZKP_ENABLED', false),
            'polygonIdEnabled' => self::bool('POLYGON_ID_ENABLED', false),
            'torFriendly' => self::bool('TOR_FRIENDLY', true),
            'retentionDays' => self::int('MESSAGE_RETENTION_DAYS', 0),
        ];
    }

    /**
     * Get moderation configuration
     *
     * @return array{autoEnabled: bool, filterLevel: string, reportThreshold: int, illegalKeywords: array, rateLimitPerMin: int, powEnabled: bool, powDifficulty: int, verificationRequired: bool}
     */
    public static function moderation(): array
    {
        $keywords = self::get('ILLEGAL_CONTENT_KEYWORDS', 'child,exploit,terrorism,weapon,drug');
        
        return [
            'autoEnabled' => self::bool('AUTO_MODERATION_ENABLED', true),
            'filterLevel' => self::get('CONTENT_FILTER_LEVEL', 'minimal'),
            'reportThreshold' => self::int('REPORT_THRESHOLD_AUTO_HIDE', 10),
            'illegalKeywords' => array_filter(explode(',', $keywords)),
            'rateLimitPerMin' => self::int('RATE_LIMIT_PER_MINUTE', 60),
            'powEnabled' => self::bool('POW_CAPTCHA_ENABLED', false),
            'powDifficulty' => self::int('POW_DIFFICULTY', 4),
            'verificationRequired' => self::bool('VERIFICATION_REQUIRED', false),
        ];
    }

    /**
     * Get VPS optimization configuration
     *
     * @return array{mysqlBufferPool: string, mysqlMaxConnections: int, phpFpmMaxChildren: int, phpFpmStartServers: int, nginxWorkerConnections: int, enableSwap: bool, useExternalServices: bool, cacheTtl: int, enableGzip: bool}
     */
    public static function vpsOptimization(): array
    {
        return [
            'mysqlBufferPool' => self::get('MYSQL_BUFFER_POOL_SIZE', '128M'),
            'mysqlMaxConnections' => self::int('MYSQL_MAX_CONNECTIONS', 50),
            'phpFpmMaxChildren' => self::int('PHP_FPM_MAX_CHILDREN', 5),
            'phpFpmStartServers' => self::int('PHP_FPM_START_SERVERS', 2),
            'nginxWorkerConnections' => self::int('NGINX_WORKER_CONNECTIONS', 512),
            'enableSwap' => self::bool('ENABLE_SWAP', true),
            'useExternalServices' => self::bool('USE_EXTERNAL_SERVICES', true),
            'cacheTtl' => self::int('CACHE_TTL', 3600),
            'enableGzip' => self::bool('ENABLE_GZIP', true),
        ];
    }
}

if (!function_exists('env')) {
    /**
     * Global helper compatible with legacy code.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    function env(string $key, $default = null)
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('env_bool')) {
    function env_bool(string $key, bool $default = false): bool
    {
        return Env::bool($key, $default);
    }
}

if (!function_exists('env_int')) {
    function env_int(string $key, int $default = 0): int
    {
        return Env::int($key, $default);
    }
}

if (!function_exists('env_float')) {
    function env_float(string $key, float $default = 0.0): float
    {
        return Env::float($key, $default);
    }
}

if (!function_exists('env_array')) {
    function env_array(string $key, string $delimiter = ',', array $default = []): array
    {
        $value = Env::get($key);

        if ($value === null || trim($value) === '') {
            return $default;
        }

        return array_values(array_filter(array_map('trim', explode($delimiter, $value)), static function ($item) {
            return $item !== '';
        }));
    }
}
