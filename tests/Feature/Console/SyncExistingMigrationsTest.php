<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SyncExistingMigrationsTest extends TestCase
{
    use DatabaseTransactions;

    private const MIGRATION = '2026_10_25_222514_create_onboarding_checklists_table';

    public function test_marks_migration_as_applied_when_table_already_exists(): void
    {
        DB::table('migrations')->where('migration', self::MIGRATION)->delete();

        $expectedBatch = ((int) DB::table('migrations')->max('batch')) + 1;

        $this->artisan('migrate:sync', ['--prefix' => self::MIGRATION])
            ->expectsOutputToContain(self::MIGRATION)
            ->assertSuccessful();

        $row = DB::table('migrations')->where('migration', self::MIGRATION)->first();

        $this->assertNotNull($row);
        $this->assertSame($expectedBatch, (int) $row->batch);
    }

    public function test_dry_run_does_not_write_to_the_migrations_table(): void
    {
        DB::table('migrations')->where('migration', self::MIGRATION)->delete();

        $this->artisan('migrate:sync', ['--prefix' => self::MIGRATION, '--dry-run' => true])
            ->expectsOutputToContain('[dry-run]')
            ->assertSuccessful();

        $this->assertFalse(
            DB::table('migrations')->where('migration', self::MIGRATION)->exists()
        );
    }

    public function test_does_nothing_when_no_pending_migration_matches_an_existing_table(): void
    {
        $this->artisan('migrate:sync', ['--prefix' => '0000_00_00_000000'])
            ->expectsOutputToContain('No pending migrations matched existing tables.')
            ->assertSuccessful();
    }

    public function test_skips_migrations_that_are_already_applied(): void
    {
        $before = DB::table('migrations')->count();

        $this->artisan('migrate:sync', ['--prefix' => self::MIGRATION])
            ->assertSuccessful();

        $this->assertSame($before, DB::table('migrations')->count());
    }

    public function test_can_force_a_single_migration_to_be_marked_as_applied(): void
    {
        DB::table('migrations')->where('migration', self::MIGRATION)->delete();

        $this->artisan('migrate:sync', ['--mark' => self::MIGRATION.'.php'])
            ->assertSuccessful();

        $this->assertTrue(
            DB::table('migrations')->where('migration', self::MIGRATION)->exists()
        );
    }

    public function test_fails_when_forced_migration_file_does_not_exist(): void
    {
        $this->artisan('migrate:sync', ['--mark' => 'does_not_exist'])
            ->expectsOutputToContain('Migration file not found')
            ->assertFailed();
    }
}
