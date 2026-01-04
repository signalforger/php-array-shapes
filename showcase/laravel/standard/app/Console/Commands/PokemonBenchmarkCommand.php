<?php

namespace App\Console\Commands;

use App\Action\GetPokemonAction;
use App\Action\ListPokemonAction;
use App\Action\Request\GetPokemonRequest;
use App\Action\Request\ListPokemonRequest;
use App\Action\Response\PokemonResponseDto;
use Illuminate\Console\Command;

/**
 * PokeAPI Benchmark - STANDARD PHP (with DTOs)
 */
class PokemonBenchmarkCommand extends Command
{
    protected $signature = 'app:pokemon-benchmark {--pokemon=20 : Number of Pokemon to fetch}';
    protected $description = 'Benchmark PokeAPI integration (standard PHP with DTOs)';

    public function handle(): int
    {
        $count = (int) $this->option('pokemon');

        $this->info("==============================================");
        $this->info("  PokeAPI Benchmark - STANDARD PHP");
        $this->info("  (with DTOs)");
        $this->info("==============================================");
        $this->info("PHP Version: " . PHP_VERSION);
        $this->info("Pokemon to fetch: " . $count);
        $this->newLine();

        $results = [];

        // Benchmark 1: List Pokemon
        $this->info("Benchmark 1: Fetching Pokemon list from API...");
        $start = hrtime(true);
        $listAction = new ListPokemonAction(new ListPokemonRequest(limit: $count));
        $listAction->execute();
        $list = $listAction->result();
        $end = hrtime(true);
        $results['list_fetch'] = ($end - $start) / 1e6;
        $this->line("  Time: " . number_format($results['list_fetch'], 2) . " ms");
        $this->line("  Pokemon in list: " . count($list->results));

        // Benchmark 2: Fetch individual Pokemon details
        $this->info("Benchmark 2: Fetching individual Pokemon details...");
        $pokemon = [];
        $start = hrtime(true);
        foreach (array_slice($list->results, 0, min(10, $count)) as $item) {
            $action = new GetPokemonAction(new GetPokemonRequest(nameOrId: $item->name));
            $action->execute();
            if (!$action->isNotFound()) {
                $pokemon[] = $action->result();
            }
        }
        $end = hrtime(true);
        $results['detail_fetch'] = ($end - $start) / 1e6;
        $this->line("  Time: " . number_format($results['detail_fetch'], 2) . " ms");
        $this->line("  Pokemon fetched: " . count($pokemon));

        // Benchmark 3: Transform Pokemon to custom format (DTOs to arrays)
        $this->info("Benchmark 3: Transforming Pokemon data (1000 iterations)...");
        $start = hrtime(true);
        for ($i = 0; $i < 1000; $i++) {
            foreach ($pokemon as $p) {
                $transformed = $this->transformPokemon($p);
            }
        }
        $end = hrtime(true);
        $results['transform'] = ($end - $start) / 1e6;
        $this->line("  Time: " . number_format($results['transform'], 2) . " ms");
        $this->line("  Transformations: " . (count($pokemon) * 1000));

        // Benchmark 4: Process stats (DTO property access)
        $this->info("Benchmark 4: Processing Pokemon stats (1000 iterations)...");
        $start = hrtime(true);
        for ($i = 0; $i < 1000; $i++) {
            foreach ($pokemon as $p) {
                $stats = $this->processStats($p);
            }
        }
        $end = hrtime(true);
        $results['stats_process'] = ($end - $start) / 1e6;
        $this->line("  Time: " . number_format($results['stats_process'], 2) . " ms");

        // Benchmark 5: Create Pokemon team compositions (nested structures)
        $this->info("Benchmark 5: Creating team compositions (1000 iterations)...");
        $start = hrtime(true);
        for ($i = 0; $i < 1000; $i++) {
            $team = $this->createTeam($pokemon);
        }
        $end = hrtime(true);
        $results['team_creation'] = ($end - $start) / 1e6;
        $this->line("  Time: " . number_format($results['team_creation'], 2) . " ms");

        // Benchmark 6: JSON serialization (requires toArray())
        $this->info("Benchmark 6: JSON serialization (1000 iterations)...");
        $start = hrtime(true);
        for ($i = 0; $i < 1000; $i++) {
            foreach ($pokemon as $p) {
                $json = json_encode($p->toArray());
            }
        }
        $end = hrtime(true);
        $results['json_serialize'] = ($end - $start) / 1e6;
        $this->line("  Time: " . number_format($results['json_serialize'], 2) . " ms");

        // Summary
        $this->newLine();
        $this->info("==============================================");
        $this->info("  SUMMARY");
        $this->info("==============================================");
        $total = array_sum($results);
        $this->line("Total time: " . number_format($total, 2) . " ms");
        $this->line("Memory peak: " . number_format(memory_get_peak_usage(true) / 1024 / 1024, 2) . " MB");

        $this->newLine();
        $this->table(
            ['Benchmark', 'Time (ms)'],
            array_map(fn($k, $v) => [$k, number_format($v, 2)], array_keys($results), $results)
        );

        // Output JSON
        $this->newLine();
        $this->line("JSON Output:");
        $this->line(json_encode([
            'variant' => 'standard',
            'framework' => 'laravel',
            'php_version' => PHP_VERSION,
            'pokemon_count' => count($pokemon),
            'results' => $results,
            'total_ms' => $total,
            'memory_mb' => memory_get_peak_usage(true) / 1024 / 1024,
        ], JSON_PRETTY_PRINT));

        return Command::SUCCESS;
    }

    /**
     * Transform Pokemon DTO to a custom array format.
     */
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

    /**
     * Process Pokemon stats from DTO.
     */
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

    /**
     * Create a Pokemon team composition from DTOs.
     */
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
