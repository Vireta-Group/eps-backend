<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncExistingMigrations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:sync
                            {--prefix= : Only sync migration files whose name starts with this prefix}
                            {--mark= : Force a single migration name to be marked as applied}
                            {--dry-run : Show what would be marked without writing to the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark create-table migrations as applied when their table already exists in the database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $prefix = (string) $this->option('prefix');
        $mark = (string) $this->option('mark');
        $dryRun = (bool) $this->option('dry-run');

        $applied = DB::table('migrations')->pluck('migration')->all();
        $batch = ((int) DB::table('migrations')->max('batch')) + 1;

        if ($mark !== '') {
            return $this->markSingle($mark, $applied, $batch, $dryRun);
        }

        $synced = 0;

        foreach (glob(database_path('migrations/*.php')) as $file) {
            $name = basename($file, '.php');

            if ($prefix !== '' && ! str_starts_with($name, $prefix)) {
                continue;
            }

            if (in_array($name, $applied, true)) {
                continue;
            }

            $table = $this->tableFor($file);

            if ($table === null || ! Schema::hasTable($table)) {
                continue;
            }

            $this->line(($dryRun ? '[dry-run] ' : '')."Marking as applied: {$name} (table: {$table})");

            if (! $dryRun) {
                DB::table('migrations')->insert([
                    'migration' => $name,
                    'batch' => $batch,
                ]);
            }

            $synced++;
        }

        $this->info($synced === 0
            ? 'No pending migrations matched existing tables.'
            : "{$synced} migration(s) ".($dryRun ? 'would be ' : '').'marked as applied.');

        return self::SUCCESS;
    }

    /**
     * Mark a single migration as applied without inspecting the schema.
     *
     * @param  array<int, string>  $applied
     */
    private function markSingle(string $mark, array $applied, int $batch, bool $dryRun): int
    {
        $name = preg_replace('/\.php$/', '', basename($mark));

        if (! is_file(database_path("migrations/{$name}.php"))) {
            $this->error("Migration file not found: {$name}");

            return self::FAILURE;
        }

        if (in_array($name, $applied, true)) {
            $this->info("Migration is already marked as applied: {$name}");

            return self::SUCCESS;
        }

        $this->line(($dryRun ? '[dry-run] ' : '')."Marking as applied: {$name}");

        if (! $dryRun) {
            DB::table('migrations')->insert([
                'migration' => $name,
                'batch' => $batch,
            ]);
        }

        $this->info($dryRun ? '1 migration(s) would be marked as applied.' : '1 migration(s) marked as applied.');

        return self::SUCCESS;
    }

    /**
     * Extract the table name created by a migration file, if any.
     */
    private function tableFor(string $file): ?string
    {
        $contents = file_get_contents($file);

        if ($contents === false) {
            return null;
        }

        if (preg_match("/Schema::create\(\s*['\"]([^'\"]+)['\"]/", $contents, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
