<?php

// The reusable preflight: the self-updater asks the consolidation for its
// verdict before running anything, so a database the consolidation would refuse
// is turned away with that refusal rather than half-upgraded.
// Spec: squash-guard-spec.md ("Reusable preflight"), platform-operations-spec.md §4.

use App\Domains\Accounts\Models\User;
use App\Platform\Operations\Database\SchemaConsolidationGuard;
use App\Platform\Operations\Update\Updater;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

/**
 * Rewrite the suite database's history into a half-migrated 2.x one.
 *
 * The schema stays exactly as it is — a complete, working database — so the
 * only thing wrong with it is the history, which is what the guard reads.
 * RefreshDatabase rolls the whole thing back after the test.
 */
function fabricateUnfinishedHistory(int $ran = 149): void
{
    DB::table('migrations')
        ->where('migration', SchemaConsolidationGuard::CONSOLIDATION_MIGRATION)
        ->delete();

    DB::table('migrations')->insert(array_map(
        fn (string $name): array => ['migration' => $name, 'batch' => 1],
        array_slice(SchemaConsolidationGuard::REPLACED_MIGRATIONS, 0, $ran)
    ));
}

it('has nothing to say about a database that already ran the consolidation', function () {
    // The suite's own database: built by the consolidation, so it is recorded.
    expect(DB::table('migrations')
        ->where('migration', SchemaConsolidationGuard::CONSOLIDATION_MIGRATION)->exists())->toBeTrue()
        ->and(SchemaConsolidationGuard::preflight())->toBeNull();
});

/**
 * Without the "already ran" check, this is the case that would break every
 * future upgrade: such a database has none of the replaced names recorded but
 * does have a schema, which the decision table reads as an inconsistency.
 */
it('does not mistake a consolidated database for an inconsistent one', function () {
    expect(SchemaConsolidationGuard::inspect()->mode())
        ->toBe(SchemaConsolidationGuard::ABORT_INCONSISTENT)
        ->and(SchemaConsolidationGuard::preflight())->toBeNull();
});

it('reports the floor verdict for a half-migrated database', function () {
    fabricateUnfinishedHistory();

    $verdict = SchemaConsolidationGuard::preflight();

    expect($verdict)->not->toBeNull()
        ->and($verdict->isAbort())->toBeTrue()
        ->and($verdict->mode())->toBe(SchemaConsolidationGuard::ABORT_FLOOR)
        ->and($verdict->recordedCount())->toBe(149)
        ->and($verdict->message())->toContain('has run 149 of the 150 historical migrations');
});

it('stops the update pipeline before any migration runs', function () {
    fabricateUnfinishedHistory(1);

    expect(fn () => Updater::migrateUpdate())->toThrow(
        RuntimeException::class,
        'Upgrading to this version requires the database to be fully migrated on the 2.x line first. '
        .'This database has run 1 of the 150 historical migrations this version consolidates. '
        .'Install the latest InvoiceShelf 2.x release, run its migrations to completion, then upgrade '
        .'to this version again. No changes have been made.'
    );
});

it('lets the update pipeline through when the consolidation has no objection', function () {
    expect(Updater::migrateUpdate())->toBeTrue();
});

it('answers the migrate endpoint with the refusal instead of a crash', function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::where('role', 'super admin')->first();
    $this->withHeaders(['company' => $user->companies()->first()->id]);
    Sanctum::actingAs($user, ['*']);

    fabricateUnfinishedHistory();

    postJson('/api/v1/update/migrate')
        ->assertStatus(500)
        ->assertJson([
            'success' => false,
            'error' => 'Upgrading to this version requires the database to be fully migrated on the 2.x line first. '
                .'This database has run 149 of the 150 historical migrations this version consolidates. '
                .'Install the latest InvoiceShelf 2.x release, run its migrations to completion, then upgrade '
                .'to this version again. No changes have been made.',
        ]);
});
