<?php

namespace App\Command;

use App\Action\GetPokemonAction;
use App\Action\ListPokemonAction;
use App\Action\Request\GetPokemonRequest;
use App\Action\Request\ListPokemonRequest;
use App\Action\Response\PokemonResponseDto;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * PokeAPI Benchmark - STANDARD PHP (with DTOs)
 */
#[AsCommand(
    name: 'app:pokemon-benchmark',
    description: 'Benchmark PokeAPI integration (standard PHP with DTOs)',
)]
class PokemonBenchmarkCommand extends Command
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('pokemon', 'p', InputOption::VALUE_OPTIONAL, 'Number of Pokemon to fetch', 20);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $count = (int) $input->getOption('pokemon');

        $io->title('PokeAPI Benchmark - STANDARD PHP');
        $io->text([
            '(with DTOs)',
            'PHP Version: ' . PHP_VERSION,
            'Pokemon to fetch: ' . $count,
        ]);

        $results = [];

        // Benchmark 1: List Pokemon
        $io->section('Benchmark 1: Fetching Pokemon list from API...');
        $start = hrtime(true);
        $listAction = new ListPokemonAction($this->httpClient, new ListPokemonRequest(limit: $count));
        $listAction->execute();
        $list = $listAction->result();
        $end = hrtime(true);
        $results['list_fetch'] = ($end - $start) / 1e6;
        $io->text([
            '  Time: ' . number_format($results['list_fetch'], 2) . ' ms',
            '  Pokemon in list: ' . count($list->results),
        ]);

        // Benchmark 2: Fetch individual Pokemon details
        $io->section('Benchmark 2: Fetching individual Pokemon details...');
        $pokemon = [];
        $start = hrtime(true);
        foreach (array_slice($list->results, 0, min(10, $count)) as $item) {
            $action = new GetPokemonAction($this->httpClient, new GetPokemonRequest(nameOrId: $item->name));
            $action->execute();
            if (!$action->isNotFound()) {
                $pokemon[] = $action->result();
            }
        }
        $end = hrtime(true);
        $results['detail_fetch'] = ($end - $start) / 1e6;
        $io->text([
            '  Time: ' . number_format($results['detail_fetch'], 2) . ' ms',
            '  Pokemon fetched: ' . count($pokemon),
        ]);

        // Benchmark 3: Transform Pokemon to custom format
        $io->section('Benchmark 3: Transforming Pokemon data (1000 iterations)...');
        $start = hrtime(true);
        for ($i = 0; $i < 1000; $i++) {
            foreach ($pokemon as $p) {
                $transformed = $this->transformPokemon($p);
            }
        }
        $end = hrtime(true);
        $results['transform'] = ($end - $start) / 1e6;
        $io->text([
            '  Time: ' . number_format($results['transform'], 2) . ' ms',
            '  Transformations: ' . (count($pokemon) * 1000),
        ]);

        // Benchmark 4: Process stats
        $io->section('Benchmark 4: Processing Pokemon stats (1000 iterations)...');
        $start = hrtime(true);
        for ($i = 0; $i < 1000; $i++) {
            foreach ($pokemon as $p) {
                $stats = $this->processStats($p);
            }
        }
        $end = hrtime(true);
        $results['stats_process'] = ($end - $start) / 1e6;
        $io->text('  Time: ' . number_format($results['stats_process'], 2) . ' ms');

        // Benchmark 5: Create team compositions
        $io->section('Benchmark 5: Creating team compositions (1000 iterations)...');
        $start = hrtime(true);
        for ($i = 0; $i < 1000; $i++) {
            $team = $this->createTeam($pokemon);
        }
        $end = hrtime(true);
        $results['team_creation'] = ($end - $start) / 1e6;
        $io->text('  Time: ' . number_format($results['team_creation'], 2) . ' ms');

        // Benchmark 6: JSON serialization (requires toArray())
        $io->section('Benchmark 6: JSON serialization (1000 iterations)...');
        $start = hrtime(true);
        for ($i = 0; $i < 1000; $i++) {
            foreach ($pokemon as $p) {
                $json = json_encode($p->toArray());
            }
        }
        $end = hrtime(true);
        $results['json_serialize'] = ($end - $start) / 1e6;
        $io->text('  Time: ' . number_format($results['json_serialize'], 2) . ' ms');

        // Summary
        $io->title('SUMMARY');
        $total = array_sum($results);
        $io->text([
            'Total time: ' . number_format($total, 2) . ' ms',
            'Memory peak: ' . number_format(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
        ]);

        $table = new Table($output);
        $table->setHeaders(['Benchmark', 'Time (ms)']);
        foreach ($results as $name => $time) {
            $table->addRow([$name, number_format($time, 2)]);
        }
        $table->render();

        $io->newLine();
        $io->text('JSON Output:');
        $io->text(json_encode([
            'variant' => 'standard',
            'framework' => 'symfony',
            'php_version' => PHP_VERSION,
            'pokemon_count' => count($pokemon),
            'results' => $results,
            'total_ms' => $total,
            'memory_mb' => memory_get_peak_usage(true) / 1024 / 1024,
        ], JSON_PRETTY_PRINT));

        return Command::SUCCESS;
    }

    private function transformPokemon(PokemonResponseDto $pokemon): array
    {
        $totalStats = 0;
        foreach ($pokemon->stats as $stat) {
            $totalStats += $stat->base_stat;
        }

        return [
            'name' => $pokemon->name,
            'display_name' => ucfirst($pokemon->name),
            'primary_type' => $pokemon->types[0]->name ?? 'unknown',
            'total_stats' => $totalStats,
            'is_strong' => $totalStats > 400,
        ];
    }

    private function processStats(PokemonResponseDto $pokemon): array
    {
        $values = [];
        $names = [];
        foreach ($pokemon->stats as $stat) {
            $values[] = $stat->base_stat;
            $names[] = $stat->name;
        }

        $maxIdx = array_search(max($values), $values);
        $minIdx = array_search(min($values), $values);

        return [
            'total' => array_sum($values),
            'average' => array_sum($values) / count($values),
            'highest' => ['name' => $names[$maxIdx], 'value' => $values[$maxIdx]],
            'lowest' => ['name' => $names[$minIdx], 'value' => $values[$minIdx]],
        ];
    }

    private function createTeam(array $pokemon): array
    {
        $members = [];
        $totalPower = 0;
        $roles = ['attacker', 'defender', 'support', 'speedster', 'all-rounder', 'tank'];

        foreach (array_slice($pokemon, 0, 6) as $i => $p) {
            $power = 0;
            foreach ($p->stats as $stat) {
                $power += $stat->base_stat;
            }
            $totalPower += $power;
            $members[] = [
                'name' => $p->name,
                'type' => $p->types[0]->name ?? 'normal',
                'role' => $roles[$i % count($roles)],
            ];
        }

        return [
            'name' => 'Benchmark Team',
            'members' => $members,
            'total_power' => $totalPower,
        ];
    }
}
