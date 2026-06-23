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
        Schema::create(DelegatedPermissions::table('role_assignments'), static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id')->constrained(DelegatedPermissions::table('roles'))->cascadeOnDelete();
            // The thing that holds the role (e.g. a User). Its scope is the role's.
            $table->morphs('authorizable');
            $table->timestamps();

            $table->unique(['role_id', 'authorizable_type', 'authorizable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(DelegatedPermissions::table('role_assignments'));
    }
};
