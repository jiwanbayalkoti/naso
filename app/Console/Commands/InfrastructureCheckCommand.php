<?php

namespace App\Console\Commands;

use App\Services\InfrastructureService;
use Illuminate\Console\Command;

class InfrastructureCheckCommand extends Command
{
    protected $signature = 'naso:infra-check';

    protected $description = 'Check database, Redis, cache, and queue infrastructure health';

    public function handle(InfrastructureService $infrastructure): int
    {
        $report = $infrastructure->check();

        $this->info('NASO Delivery — Infrastructure Check');
        $this->newLine();

        $this->line('PHP: '.$report['php_version']
            .($report['php_meets_laravel_13'] ? ' (Laravel 13 ready)' : ' (upgrade to 8.3+ for Laravel 13)'));

        $this->renderComponent('Database', $report['database']);
        $this->renderComponent('Redis', $report['redis']);
        $this->renderComponent('Cache', $report['cache']);
        $this->renderComponent('Queue', $report['queue']);

        $this->line('Horizon (pcntl): '.($report['horizon_available'] ? 'available' : 'not available on Windows'));

        if (! empty($report['recommendations'])) {
            $this->newLine();
            $this->warn('Recommendations:');
            foreach ($report['recommendations'] as $recommendation) {
                $this->line('  • '.$recommendation);
            }
        }

        $hasErrors = collect([$report['database'], $report['redis'], $report['cache']])
            ->contains(fn (array $item) => ($item['status'] ?? '') === 'error' && ($item['driver'] ?? '') !== 'file');

        return $hasErrors && config('cache.default') === 'redis' ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function renderComponent(string $label, array $data): void
    {
        $status = $data['status'] ?? 'unknown';
        $icon = $status === 'ok' ? '<fg=green>✓</>' : '<fg=red>✗</>';

        $details = collect($data)
            ->except(['status', 'message'])
            ->map(fn ($value, $key) => "{$key}={$value}")
            ->implode(', ');

        $message = $data['message'] ?? $details;
        $this->line("{$icon} {$label}: {$message}");
    }
}
