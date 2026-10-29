<?php

namespace App\Domains\Metadata;

use App\Domains\Metadata\Application\EloquentCustomFieldValueWriter;
use App\Domains\Metadata\Contracts\CustomFieldValueWriter;
use App\Domains\Metadata\Models\CustomField;
use App\Domains\Metadata\Models\Note;
use App\Domains\Metadata\Policies\CustomFieldPolicy;
use App\Domains\Metadata\Policies\NotePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class MetadataServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CustomFieldValueWriter::class, EloquentCustomFieldValueWriter::class);
    }

    public function boot(): void
    {
        Gate::policy(CustomField::class, CustomFieldPolicy::class);
        Gate::policy(Note::class, NotePolicy::class);
        $noteAbilities = [
            'manage notes' => 'manageNotes',
            'view notes' => 'viewNotes',
        ];
        foreach ($noteAbilities as $ability => $method) {
            Gate::define($ability, [NotePolicy::class, $method]);
        }
    }
}
