<?php

declare(strict_types=1);

use Fanmade\DelegatedPermissions\Models\Permission;
use Fanmade\DelegatedPermissions\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->base = sys_get_temp_dir().'/ldp-install-'.bin2hex(random_bytes(4));
    mkdir($this->base.'/app', 0755, true);
    mkdir($this->base.'/resources/views', 0755, true);
    mkdir($this->base.'/config', 0755, true);

    // The install command derives the app namespace from composer.json.
    file_put_contents(
        $this->base.'/composer.json',
        json_encode(['autoload' => ['psr-4' => ['App\\' => 'app/']]], JSON_THROW_ON_ERROR),
    );

    $this->app->setBasePath($this->base);
});

afterEach(function () {
    removeDir($this->base);
});

it('seeds the management permissions and a single system role', function () {
    $this->artisan('delegated-permissions:install', ['variant' => 'livewire', '--no-migrate' => true])
        ->assertSuccessful();

    expect(Permission::where('name', 'create-roles')->exists())->toBeTrue()
        ->and(Permission::where('name', 'delete-groups')->exists())->toBeTrue()
        ->and(Role::query()->where('is_system', true)->whereNull('scope_type')->count())->toBe(1);
});

it('scaffolds the Livewire component with the app namespace and the plain view', function () {
    $this->artisan('delegated-permissions:install', ['variant' => 'livewire', '--no-migrate' => true])
        ->assertSuccessful();

    $component = $this->base.'/app/Livewire/DelegatedPermissions/DelegatedRoles.php';
    $view = $this->base.'/resources/views/delegated-permissions/delegated-roles.blade.php';

    expect(file_exists($component))->toBeTrue()
        ->and(file_get_contents($component))->toContain('namespace App\\Livewire\\DelegatedPermissions;')
        ->and(file_get_contents($component))->not->toContain('{{ namespace }}')
        ->and(file_exists($view))->toBeTrue()
        ->and(file_get_contents($view))->not->toContain('flux:');
});

it('scaffolds the Flux view variant when chosen', function () {
    $this->artisan('delegated-permissions:install', ['variant' => 'livewire-flux', '--no-migrate' => true])
        ->assertSuccessful();

    $view = $this->base.'/resources/views/delegated-permissions/delegated-roles.blade.php';

    expect(file_get_contents($view))->toContain('flux:');
});

it('runs under the ldp:install alias', function () {
    $this->artisan('ldp:install', ['variant' => 'livewire', '--no-migrate' => true])
        ->assertSuccessful();

    expect(Permission::where('name', 'create-roles')->exists())->toBeTrue();
});

it('rejects an unknown variant', function () {
    $this->artisan('delegated-permissions:install', ['variant' => 'angular', '--no-migrate' => true])
        ->assertFailed();
});

it('does not create a second system role on re-install', function () {
    $this->artisan('delegated-permissions:install', ['variant' => 'livewire', '--no-migrate' => true])->assertSuccessful();
    $this->artisan('delegated-permissions:install', ['variant' => 'livewire', '--force' => true, '--no-migrate' => true])->assertSuccessful();

    expect(Role::query()->where('is_system', true)->whereNull('scope_type')->count())->toBe(1);
});
