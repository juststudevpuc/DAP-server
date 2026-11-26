<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class MigrateFromAiven extends Command
{
    protected $signature = 'db:migrate-from-aiven';
    protected $description = 'Read-only copy from Aiven MySQL into the active PostgreSQL (Neon) database';

    /**
     * Tables copied in parent-before-child foreign key order.
     * System tables (migrations, sessions, jobs, failed_jobs, job_batches, cache_locks) are skipped.
     */
    private array $orderedTables = [
        'users',
        'weekly_action_plans',
        'daily_metrics',
        'team_summary_notes',
        'personal_access_tokens',
        'password_reset_tokens',
        'cache',
    ];

    private array $ignoredSystemTables = [
        'migrations',
        'sessions',
        'jobs',
        'job_batches',
        'failed_jobs',
        'cache_locks',
    ];

    public function handle(): int
    {
        $source = DB::connection('aiven_mysql');
        $target = DB::connection('pgsql');

        $sourceHost = config('database.connections.aiven_mysql.host');
        $sourceDb   = config('database.connections.aiven_mysql.database');
        $targetHost = config('database.connections.pgsql.host');
        $targetDb   = config('database.connections.pgsql.database');

        $this->info("Source (READ-ONLY): {$sourceHost} / {$sourceDb}");
        $this->info("Target (OVERWRITE): {$targetHost} / {$targetDb}");

        // 1. Verify both connections & compare table lists
        $aivenTables = collect($source->select(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = ? AND table_type = 'BASE TABLE'",
            [$sourceDb]
        ))->map(fn ($row) => array_change_key_case((array) $row, CASE_LOWER)['table_name'])->all();

        $neonTables = collect($target->select(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'"
        ))->map(fn ($row) => $row->table_name)->all();

        // Check for any un-migrated tables sitting in Aiven (e.g., settings)
        $untrackedAivenTables = array_diff(
            $aivenTables,
            $this->orderedTables,
            $this->ignoredSystemTables
        );

        if (!empty($untrackedAivenTables)) {
            $this->warn('Aiven has extra tables not in the copy list: ' . implode(', ', $untrackedAivenTables));
            if (!$this->confirm('Continue without copying those extra tables?', false)) {
                $this->line('Aborted so you can inspect or migrate those tables first.');
                return self::FAILURE;
            }
        }

        $tablesToCopy = array_values(array_filter(
            $this->orderedTables,
            fn ($t) => in_array($t, $aivenTables, true) && in_array($t, $neonTables, true)
        ));

        $this->newLine();
        $this->line('Tables queued for copy: ' . implode(', ', $tablesToCopy));

        if (!$this->confirm("This will TRUNCATE those tables on Neon ({$targetHost}) and copy Aiven rows into them. Aiven will NOT be modified. Proceed?", false)) {
            $this->line('Cancelled.');
            return self::SUCCESS;
        }

        try {
            $target->transaction(function () use ($source, $target, $tablesToCopy) {
                // Wipe Phase 3 test rows on Neon cleanly
                $quoted = implode(', ', array_map(fn ($t) => "\"{$t}\"", $tablesToCopy));
                $target->statement("TRUNCATE TABLE {$quoted} RESTART IDENTITY CASCADE");

                foreach ($tablesToCopy as $table) {
                    // Inspect target Postgres columns & data types
                    $pgColumns = collect($target->select(
                        "SELECT column_name, data_type FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ?",
                        [$table]
                    ))->pluck('data_type', 'column_name')->all();

                    $rows = $source->table($table)->get();

                    if ($rows->isEmpty()) {
                        $this->line("  - {$table}: 0 rows (skipped)");
                        continue;
                    }

                    $prepared = $rows->map(function ($row) use ($pgColumns) {
                        $item = [];
                        foreach ((array) $row as $col => $val) {
                            if (!array_key_exists($col, $pgColumns)) {
                                continue;
                            }

                            $type = $pgColumns[$col];

                            if ($val !== null && $type === 'boolean') {
                                $val = (bool) $val;
                            } elseif ($val === '0000-00-00' || $val === '0000-00-00 00:00:00') {
                                $val = null;
                            }

                            $item[$col] = $val;
                        }
                        return $item;
                    });

                    foreach ($prepared->chunk(200) as $chunk) {
                        $target->table($table)->insert($chunk->all());
                    }

                    // Reset PostgreSQL primary-key sequence if the table has an 'id' column
                    if (array_key_exists('id', $pgColumns)) {
                        $seqRow = $target->selectOne("SELECT pg_get_serial_sequence('public.\"{$table}\"', 'id') AS seq");
                        if ($seqRow && $seqRow->seq) {
                            $maxId = $target->table($table)->max('id');
                            if ($maxId !== null) {
                                $target->statement("SELECT setval(?, ?, true)", [$seqRow->seq, (int) $maxId]);
                            } else {
                                $target->statement("SELECT setval(?, 1, false)", [$seqRow->seq]);
                            }
                        }
                    }

                    $this->info("  ✓ {$table}: copied {$rows->count()} rows");
                }
            });
        } catch (Throwable $e) {
            $this->error('Migration rolled back — Neon was left unchanged.');
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        // 2. Side-by-side verification table
        $this->newLine();
        $summary = [];
        foreach ($tablesToCopy as $table) {
            $aivenCount = $source->table($table)->count();
            $neonCount  = $target->table($table)->count();
            $summary[] = [
                $table,
                $aivenCount,
                $neonCount,
                $aivenCount === $neonCount ? 'MATCH' : 'MISMATCH',
            ];
        }

        $this->table(['Table', 'Aiven MySQL', 'Neon Postgres', 'Status'], $summary);
        $this->info('Dry run complete!');

        return self::SUCCESS;
    }
}
