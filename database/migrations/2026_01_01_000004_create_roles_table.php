<?php

declare(strict_types=1);

use Fanmade\DelegatedPermissions\DelegatedPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(DelegatedPermissions::table('roles'), static function (Blueprint $table): void {
            $table->id();
            // Self-referencing parent; null only for the system root of a scope.
            $table->foreignId('parent_id')->nullable()->constrained(DelegatedPermissions::table('roles'))->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            // The scope the role belongs to (e.g. a Project), or null = global.
            $table->nullableMorphs('scope');
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            // Role names are unique within a scope.
            $table->unique(['scope_type', 'scope_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(DelegatedPermissions::table('roles'));
    }
};
