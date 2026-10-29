<?php

use Illuminate\Support\Facades\DB;

test('the model identity migration covers every persisted morph column', function () {
    $migration = require database_path('migrations/2026_08_05_120000_stabilize_model_type_aliases.php');

    expect($migration::COLUMNS)->toBe([
        'media' => ['model_type'],
        'email_logs' => ['mailable_type'],
        'notifications' => ['notifiable_type'],
        'personal_access_tokens' => ['tokenable_type'],
        'custom_field_values' => ['custom_field_valuable_type'],
        'abilities' => ['entity_type'],
        'assigned_roles' => ['entity_type', 'restricted_to_type'],
        'permissions' => ['entity_type'],
    ]);
});

test('legacy model types migrate to stable aliases and can be rolled back', function () {
    $abilityId = DB::table('abilities')->insertGetId([
        'name' => 'architecture-migration-test',
        'entity_type' => 'InvoiceShelf\\Models\\Invoice',
        'only_owned' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require database_path('migrations/2026_08_05_120000_stabilize_model_type_aliases.php');
    $migration->up();

    expect(DB::table('abilities')->where('id', $abilityId)->value('entity_type'))->toBe('invoice');

    $migration->down();

    expect(DB::table('abilities')->where('id', $abilityId)->value('entity_type'))->toBe('App\\Models\\Invoice');
});

test('legacy bouncer role identities migrate and can be rolled back', function () {
    $roleId = DB::table('roles')->insertGetId([
        'name' => 'architecture-migration-role',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $abilityId = DB::table('abilities')->insertGetId([
        'name' => 'architecture-migration-ability',
        'only_owned' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $permissionId = DB::table('permissions')->insertGetId([
        'ability_id' => $abilityId,
        'entity_id' => $roleId,
        'entity_type' => 'roles',
        'forbidden' => false,
    ]);

    $migration = require database_path('migrations/2026_08_05_120000_stabilize_model_type_aliases.php');
    $migration->up();

    expect(DB::table('permissions')->where('id', $permissionId)->value('entity_type'))
        ->toBe('bouncer_role');

    $migration->down();

    expect(DB::table('permissions')->where('id', $permissionId)->value('entity_type'))
        ->toBe('roles');
});

test('unknown model identities are left untouched', function () {
    $abilityId = DB::table('abilities')->insertGetId([
        'name' => 'architecture-unknown-test',
        'entity_type' => 'Modules\\Example\\Models\\Record',
        'only_owned' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require database_path('migrations/2026_08_05_120000_stabilize_model_type_aliases.php');
    $migration->up();

    expect(DB::table('abilities')->where('id', $abilityId)->value('entity_type'))
        ->toBe('Modules\\Example\\Models\\Record');
});
