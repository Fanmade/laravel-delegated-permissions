<?php

declare(strict_types=1);

use Fanmade\DelegatedPermissions\Scaffolding\StubScaffolder;

function removeDir(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $path = $dir.'/'.$entry;
        is_dir($path) ? removeDir($path) : unlink($path);
    }

    rmdir($dir);
}

beforeEach(function () {
    $this->tmp = sys_get_temp_dir().'/ldp-scaffold-'.bin2hex(random_bytes(4));
    $this->source = $this->tmp.'/source';
    $this->target = $this->tmp.'/target';

    mkdir($this->source.'/sub', 0755, true);
    file_put_contents($this->source.'/Foo.php.stub', 'namespace {{ namespace }};');
    file_put_contents($this->source.'/sub/bar.blade.php.stub', 'hello');
    file_put_contents($this->source.'/ignore.txt', 'not a stub');
});

afterEach(function () {
    removeDir($this->tmp);
});

it('copies stubs, strips the .stub suffix and rewrites tokens', function () {
    $result = (new StubScaffolder)->copy($this->source, $this->target, ['{{ namespace }}' => 'App\\Foo']);

    expect(file_exists($this->target.'/Foo.php'))->toBeTrue()
        ->and(file_get_contents($this->target.'/Foo.php'))->toBe('namespace App\\Foo;')
        ->and(file_exists($this->target.'/sub/bar.blade.php'))->toBeTrue()
        ->and(file_exists($this->target.'/ignore.txt'))->toBeFalse() // non-stub ignored
        ->and($result['written'])->toHaveCount(2)
        ->and($result['skipped'])->toBeEmpty();
});

it('skips existing files unless forced', function () {
    mkdir($this->target, 0755, true);
    file_put_contents($this->target.'/Foo.php', 'existing');

    $skip = (new StubScaffolder)->copy($this->source, $this->target, ['{{ namespace }}' => 'App\\Foo']);

    expect($skip['skipped'])->toContain($this->target.'/Foo.php')
        ->and(file_get_contents($this->target.'/Foo.php'))->toBe('existing');

    $forced = (new StubScaffolder)->copy($this->source, $this->target, ['{{ namespace }}' => 'X'], true);

    expect($forced['written'])->toContain($this->target.'/Foo.php')
        ->and(file_get_contents($this->target.'/Foo.php'))->toBe('namespace X;');
});
