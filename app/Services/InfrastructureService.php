<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Throwable;

class InfrastructureService
{
    /**
     * @return array<string, mixed>
     */
    public function check(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'php_meets_laravel_13' => version_compare(PHP_VERSION, '8.3.0', '>='),
            'php_meets_reverb' => version_compare(PHP_VERSION, '8.2.0', '>='),
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'horizon_available' => extension_loaded('pcntl'),
            'recommendations' => $this->recommendations(),
        ];
    }

    public function isRedisAvailable(): bool
    {
        return $this->checkRedis()['status'] === 'ok';
    }

    /**
     * @return array<string, mixed>
     */
    protected function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return [
                'status' => 'ok',
                'driver' => config('database.default'),
                'database' => config('database.connections.'.config('database.default').'.database'),
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'error',
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function checkRedis(): array
    {
        try {
            $ping = Redis::connection()->ping();

            return [
                'status' => 'ok',
                'client' => config('database.redis.client'),
                'host' => config('database.redis.default.host'),
                'port' => config('database.redis.default.port'),
                'response' => is_string($ping) ? $ping : 'PONG',
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'error',
                'client' => config('database.redis.client'),
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function checkCache(): array
    {
        $driver = config('cache.default');

        try {
            $key = 'naso_infra_check_'.uniqid();
            Cache::put($key, true, 10);
            $value = Cache::get($key);
            Cache::forget($key);

            return [
                'status' => $value ? 'ok' : 'error',
                'driver' => $driver,
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'error',
                'driver' => $driver,
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function checkQueue(): array
    {
        $connection = config('queue.default');

        return [
            'status' => 'ok',
            'connection' => $connection,
            'driver' => config("queue.connections.{$connection}.driver"),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function recommendations(): array
    {
        $items = [];

        if (version_compare(PHP_VERSION, '8.3.0', '<')) {
            $items[] = 'Upgrade PHP to 8.3+ for Laravel 13 (run scripts/upgrade-php-xampp.ps1).';
        }

        if (! $this->isRedisAvailable()) {
            $items[] = 'Install Redis/Memurai and set CACHE_DRIVER=redis, QUEUE_CONNECTION=redis (run scripts/install-redis-windows.ps1).';
        }

        if (! extension_loaded('pcntl')) {
            $items[] = 'Laravel Horizon requires Linux/macOS (pcntl). On Windows use: php artisan queue:work database';
        }

        if (config('broadcasting.default') === 'log') {
            $items[] = 'After PHP 8.2+, install Laravel Reverb for real-time WebSocket updates.';
        }

        return $items;
    }
}
