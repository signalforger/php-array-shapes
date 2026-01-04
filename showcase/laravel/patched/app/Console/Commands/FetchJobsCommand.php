<?php

namespace App\Console\Commands;

use App\Services\JobAggregatorService;
use Illuminate\Console\Command;

class FetchJobsCommand extends Command
{
    protected $signature = 'jobs:fetch
        {--provider= : Specific provider to fetch from (remotive, arbeitnow, jsearch)}
        {--query= : Search query for providers that support it}';

    protected $description = 'Fetch jobs from external APIs';

    public function handle(JobAggregatorService $aggregator): int
    {
        $provider = $this->option('provider');
        $options = [];

        if ($query = $this->option('query')) {
            $options['query'] = $query;
            $options['search'] = $query;
        }

        if ($provider) {
            $this->info("Fetching jobs from {$provider}...");
            $stats = $aggregator->fetchFromProvider($provider, $options);
        } else {
            $this->info('Fetching jobs from all providers...');
            $stats = $aggregator->fetchAndSaveAll($options);
        }

        $this->newLine();
        $this->info('Results:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Fetched', $stats['fetched']],
                ['Saved', $stats['saved']],
                ['Errors', $stats['errors']],
            ]
        );

        if (isset($stats['by_provider'])) {
            $this->newLine();
            $this->info('By Provider:');
            $rows = [];
            foreach ($stats['by_provider'] as $name => $s) {
                $rows[] = [$name, $s['fetched'], $s['saved'], $s['errors']];
            }
            $this->table(['Provider', 'Fetched', 'Saved', 'Errors'], $rows);
        }

        return $stats['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
