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
        Schema::create(DelegatedPermissions::table('permission_role'), static function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained(DelegatedPermissions::table('roles'))->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained(DelegatedPermissions::table('permissions'))->cascadeOnDelete();

            $table->primary(['role_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(DelegatedPermissions::table('permission_role'));
    }
};
