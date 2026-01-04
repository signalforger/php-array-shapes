<?php

namespace App\Command;

use App\Service\JobAggregatorService;
use App\Service\JobProvider\RemotiveProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fetch-jobs',
    description: 'Fetch jobs from all configured providers',
)]
class FetchJobsCommand extends Command
{
    public function __construct(
        private readonly JobAggregatorService $aggregator,
        private readonly RemotiveProvider $remotiveProvider
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Fetching Jobs from External APIs');

        // Add providers
        $this->aggregator->addProvider($this->remotiveProvider);

        $io->info('Starting job fetch...');

        $results = $this->aggregator->fetchAllJobs();

        foreach ($results as $provider => $result) {
            if (isset($result['error'])) {
                $io->error("$provider: {$result['error']}");
            } else {
                $io->success("$provider: Fetched {$result['fetched']}, Saved {$result['saved']}, Updated {$result['updated']}");
            }
        }

        $io->success('Job fetch completed!');

        return Command::SUCCESS;
    }
}
