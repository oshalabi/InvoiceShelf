<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_credentials', function (Blueprint $table) {
            $table->id();
            // Encrypted opaque installation token; never an account token.
            $table->text('credential');
            $table->string('device_id')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paired_at')->nullable();
            $table->timestamps();
        });

        Schema::create('marketplace_operations', function (Blueprint $table) {
            $table->id();
            $table->string('lock_name')->nullable()->unique();
            $table->string('slug')->nullable();
            $table->string('version')->nullable();
            $table->string('channel')->nullable();
            $table->string('status')->default('running');
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::table('modules', function (Blueprint $table) {
            $table->unique('name');
            $table->string('slug')->nullable()->index();
            $table->string('state')->default('installed')->index();
            $table->text('last_error')->nullable();
            $table->timestamp('last_failed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->dropColumn(['slug', 'state', 'last_error', 'last_failed_at']);
        });

        Schema::dropIfExists('marketplace_operations');
        Schema::dropIfExists('marketplace_credentials');
    }
};
