<?php

namespace App\Console\Commands;

use App\Services\WebhookService;
use Illuminate\Console\Command;

class ProcessWebhooksCommand extends Command
{
    protected $signature = 'webhooks:process
        {--retry : Also retry failed deliveries}';

    protected $description = 'Process saved searches and send webhook notifications';

    public function handle(WebhookService $webhookService): int
    {
        $this->info('Processing saved searches...');

        $stats = $webhookService->processAllSavedSearches();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Processed', $stats['processed']],
                ['Delivered', $stats['delivered']],
                ['Failed', $stats['failed']],
            ]
        );

        if ($this->option('retry')) {
            $this->newLine();
            $this->info('Retrying failed deliveries...');

            $retryStats = $webhookService->retryFailedDeliveries();

            $this->table(
                ['Metric', 'Count'],
                [
                    ['Retried', $retryStats['retried']],
                    ['Delivered', $retryStats['delivered']],
                    ['Failed', $retryStats['failed']],
                ]
            );
        }

        return Command::SUCCESS;
    }
}
