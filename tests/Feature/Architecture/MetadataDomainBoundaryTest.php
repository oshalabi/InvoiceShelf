<?php

use App\Domains\Metadata\Application\EloquentCustomFieldValueWriter;
use App\Domains\Metadata\Contracts\CustomFieldValueWriter;
use App\Domains\Metadata\MetadataServiceProvider;
use App\Domains\Metadata\Models\CustomField;
use App\Domains\Metadata\Models\Note;
use App\Domains\Metadata\Policies\CustomFieldPolicy;
use App\Domains\Metadata\Policies\NotePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

test('the metadata domain owns custom fields notes and authorization', function () {
    expect(app()->getProviders(MetadataServiceProvider::class))->toHaveCount(1)
        ->and(app(CustomFieldValueWriter::class))->toBeInstanceOf(EloquentCustomFieldValueWriter::class)
        ->and(Gate::getPolicyFor(CustomField::class))->toBeInstanceOf(CustomFieldPolicy::class)
        ->and(Gate::getPolicyFor(Note::class))->toBeInstanceOf(NotePolicy::class)
        ->and(Gate::has('manage notes'))->toBeTrue()
        ->and(Gate::has('view notes'))->toBeTrue();

    expect(class_exists('App\\Services\\CustomFieldService'))->toBeFalse()
        ->and(trait_exists('App\\Traits\\HasCustomFieldsTrait'))->toBeFalse()
        ->and(class_exists('App\\Policies\\CustomFieldPolicy'))->toBeFalse()
        ->and(class_exists('App\\Policies\\NotePolicy'))->toBeFalse()
        ->and(class_exists('App\\Http\\Controllers\\Company\\CustomField\\CustomFieldsController'))->toBeFalse()
        ->and(class_exists('App\\Http\\Controllers\\Company\\General\\NotesController'))->toBeFalse()
        ->and(class_exists('App\\Http\\Requests\\CustomFieldRequest'))->toBeFalse()
        ->and(class_exists('App\\Http\\Requests\\NotesRequest'))->toBeFalse()
        ->and(class_exists('App\\Http\\Resources\\CustomFieldResource'))->toBeFalse()
        ->and(class_exists('App\\Http\\Resources\\CustomFieldValueResource'))->toBeFalse()
        ->and(class_exists('App\\Http\\Resources\\Customer\\CustomFieldResource'))->toBeFalse()
        ->and(class_exists('App\\Http\\Resources\\Customer\\CustomFieldValueResource'))->toBeFalse()
        ->and(class_exists('App\\Http\\Resources\\NoteResource'))->toBeFalse()
        ->and(class_exists('App\\Http\\Resources\\CustomFieldCollection'))->toBeFalse()
        ->and(class_exists('App\\Http\\Resources\\CustomFieldValueCollection'))->toBeFalse()
        ->and(class_exists('App\\Http\\Resources\\NoteCollection'))->toBeFalse();
});

test('the metadata domain preserves custom field and note routes and middleware', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => preg_match('#^api/v1/(?:custom-fields|notes)(?:$|/)#', $route->uri()) === 1)
        ->keyBy(fn ($route): string => implode('|', $route->methods()).' '.$route->uri());

    expect($routes->keys()->sort()->values()->all())->toBe(collect([
        'DELETE api/v1/custom-fields/{custom_field}',
        'DELETE api/v1/notes/{note}',
        'GET|HEAD api/v1/custom-fields',
        'GET|HEAD api/v1/custom-fields/create',
        'GET|HEAD api/v1/custom-fields/{custom_field}',
        'GET|HEAD api/v1/custom-fields/{custom_field}/edit',
        'GET|HEAD api/v1/notes',
        'GET|HEAD api/v1/notes/{note}',
        'POST api/v1/custom-fields',
        'POST api/v1/notes',
        'PUT|PATCH api/v1/custom-fields/{custom_field}',
        'PUT|PATCH api/v1/notes/{note}',
    ])->sort()->values()->all());

    foreach ($routes as $route) {
        expect($route->getActionName())
            ->toStartWith('App\\Domains\\Metadata\\Http\\Controllers\\')
            ->and($route->gatherMiddleware())->toContain('auth:sanctum', 'company', 'bouncer');
    }
});
