<?php

namespace App\Console\Commands;

use App\Models\SensorLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deduplica i sensor_log esistenti nel database.
 *
 * Logica: per ogni (dispenser_id, ora) mantiene SOLO il primo record inserito
 * (quello con l'id piu' basso) ed elimina tutti gli altri.
 * Utile per ripulire i dati accumulati prima dell'introduzione del throttle orario.
 */
class DeduplicateSensorLogsCommand extends Command
{
    protected $signature = 'sensor-logs:deduplicate
                            {--dry-run : Mostra quanti record verrebbero eliminati senza cancellare nulla}
                            {--dispenser= : Limita la pulizia a un singolo dispenser_id}';

    protected $description = 'Elimina i sensor_log duplicati tenendo 1 record per ora per dispenser';

    public function handle(): int
    {
        $dispenserFilter = $this->option('dispenser');
        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun
            ? '=== Modalita DRY-RUN: nessuna cancellazione effettuata ==='
            : '=== Deduplicazione sensor_log ==='
        );

        // Trova gli ID da TENERE: il minimo id per ogni (dispenser_id, ora)
        $keepQuery = SensorLog::query()
            ->select(DB::raw('MIN(id) as id'))
            ->groupBy('dispenser_id', DB::raw("DATE_FORMAT(recorded_at, '%Y-%m-%d %H')"));

        if ($dispenserFilter !== null) {
            $keepQuery->where('dispenser_id', (int) $dispenserFilter);
        }

        $idsToKeep = $keepQuery->pluck('id');

        // Conta i duplicati da eliminare
        $deleteQuery = SensorLog::query()->whereNotIn('id', $idsToKeep);

        if ($dispenserFilter !== null) {
            $deleteQuery->where('dispenser_id', (int) $dispenserFilter);
        }

        $totalToDelete = $deleteQuery->count();
        $totalToKeep   = $idsToKeep->count();

        $this->line("  Record da mantenere  : {$totalToKeep}");
        $this->line("  Record da eliminare  : {$totalToDelete}");

        if ($totalToDelete === 0) {
            $this->info('Nessun duplicato trovato. Tutto in ordine.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn("DRY-RUN: {$totalToDelete} record sarebbero eliminati. Riesegui senza --dry-run per applicare.");
            return self::SUCCESS;
        }

        if (! $this->confirm("Eliminare {$totalToDelete} record duplicati?", true)) {
            $this->info('Operazione annullata.');
            return self::SUCCESS;
        }

        // Elimina in batch da 500 per non bloccare il DB
        $deleted = 0;
        $bar = $this->output->createProgressBar((int) ceil($totalToDelete / 500));
        $bar->start();

        do {
            $chunk = SensorLog::query()
                ->whereNotIn('id', $idsToKeep)
                ->when($dispenserFilter !== null, fn ($q) => $q->where('dispenser_id', (int) $dispenserFilter))
                ->limit(500)
                ->pluck('id');

            if ($chunk->isEmpty()) {
                break;
            }

            $count = SensorLog::query()->whereIn('id', $chunk)->delete();
            $deleted += $count;
            $bar->advance();
        } while ($chunk->count() === 500);

        $bar->finish();
        $this->newLine();
        $this->info("Eliminati {$deleted} record duplicati. Rimangono {$totalToKeep} log (1 per ora per dispenser).");

        return self::SUCCESS;
    }
}
