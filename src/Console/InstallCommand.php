<?php

declare(strict_types=1);

namespace Fanmade\DelegatedPermissions\Console;

use Fanmade\DelegatedPermissions\Models\Role;
use Fanmade\DelegatedPermissions\PermissionManager;
use Fanmade\DelegatedPermissions\RoleManager;
use Fanmade\DelegatedPermissions\Scaffolding\StubScaffolder;
use Illuminate\Console\Command;

use function Laravel\Prompts\select;

class InstallCommand extends Command
{
    protected $signature = 'delegated-permissions:install
        {variant? : The UI variant to scaffold (livewire, livewire-flux)}
        {--force : Overwrite existing files}
        {--no-migrate : Skip running migrations}
        {--no-seed : Skip seeding management permissions and the system role}';

    protected $description = 'Install Delegated Permissions: config, migrations, a system role, and a UI variant.';

    /**
     * @var array<int, string>
     */
    protected $aliases = ['ldp:install'];

    /**
     * @var array<string, string>
     */
    private const VARIANTS = [
        'livewire' => 'Livewire (plain Tailwind)',
        'livewire-flux' => 'Livewire (Flux)',
    ];

    public function handle(StubScaffolder $scaffolder, PermissionManager $permissions, RoleManager $roles): int
    {
        $variant = $this->resolveVariant();

        if ($variant === null) {
            $this->components->error('Unknown variant. Choose one of: '.implode(', ', array_keys(self::VARIANTS)).'.');

            return self::FAILURE;
        }

        $this->callSilently('vendor:publish', [
            '--tag' => 'delegated-permissions-config',
            '--force' => (bool) $this->option('force'),
        ]);
        $this->components->info('Published the config.');

        if (! $this->option('no-migrate')) {
            $this->call('migrate', ['--force' => true]);
        }

        if (! $this->option('no-seed')) {
            $permissions->installManagementPermissions();

            if (! Role::query()->where('is_system', true)->whereNull('scope_type')->exists()) {
                $roles->createSystemRole();
            }

            $this->components->info('Seeded the management permissions and the system role.');
        }

        $this->scaffold($scaffolder, $variant);

        $this->newLine();
        $this->components->info('Delegated Permissions installed.');
        $this->line('  • Components:  <comment>app/Livewire/DelegatedPermissions/</comment>');
        $this->line('  • Views:       <comment>resources/views/delegated-permissions/</comment>');
        $this->line('  • Drop one in a Blade view, e.g. <comment>&lt;livewire:delegated-permissions.delegated-roles /&gt;</comment>');
        $this->line('  • Grant the management permissions to an admin role, then gate with <comment>$user->can(\'create-roles\')</comment>.');

        return self::SUCCESS;
    }

    private function resolveVariant(): ?string
    {
        $variant = $this->argument('variant');

        if ($variant === null) {
            $variant = $this->input->isInteractive()
                ? select('Which UI variant would you like to install?', self::VARIANTS, 'livewire')
                : 'livewire';
        }

        return array_key_exists($variant, self::VARIANTS) ? $variant : null;
    }

    private function scaffold(StubScaffolder $scaffolder, string $variant): void
    {
        $namespace = rtrim($this->laravel->getNamespace(), '\\').'\\Livewire\\DelegatedPermissions';
        $view = $variant === 'livewire-flux' ? 'flux' : 'plain';

        if ($view === 'flux' && ! class_exists('Flux\\Flux')) {
            $this->components->warn('The Flux variant assumes livewire/flux is installed — it is not, so the views will not render until you add it.');
        }

        $base = dirname(__DIR__, 2).'/resources/stubs/livewire';

        $classes = $scaffolder->copy(
            $base.'/classes',
            $this->laravel->path('Livewire/DelegatedPermissions'),
            ['{{ namespace }}' => $namespace],
            (bool) $this->option('force'),
        );

        $views = $scaffolder->copy(
            $base.'/views/'.$view,
            $this->laravel->resourcePath('views/delegated-permissions'),
            [],
            (bool) $this->option('force'),
        );

        $this->report('Components', $classes);
        $this->report('Views', $views);
    }

    /**
     * @param  array{written: array<int, string>, skipped: array<int, string>}  $result
     */
    private function report(string $label, array $result): void
    {
        foreach ($result['written'] as $path) {
            $this->components->task($label.': '.basename($path));
        }

        foreach ($result['skipped'] as $path) {
            $this->components->warn($label.' exists, skipped: '.basename($path).' (use --force to overwrite)');
        }
    }
}
