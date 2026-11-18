<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->configDirectory = sys_get_temp_dir().'/orphan-storage-install-'.uniqid();
    File::ensureDirectoryExists($this->configDirectory);
    $this->app->useConfigPath($this->configDirectory);

    $this->packageConfig = (string) file_get_contents(dirname(__DIR__, 2).'/config/orphan-storage.php');
    $this->overwriteQuestion = 'config/orphan-storage.php already exists. Overwrite it with the package defaults?';
});

afterEach(function () {
    File::deleteDirectory($this->configDirectory);
});

it('publishes the config file and prints the next steps without touching the database', function () {
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $this->artisan('orphan:install')
        ->expectsOutputToContain('Published config/orphan-storage.php.')
        ->expectsOutputToContain('php artisan orphan:scan')
        ->expectsOutputToContain('php artisan orphan:clean --force')
        ->assertSuccessful();

    expect(file_get_contents(config_path('orphan-storage.php')))->toBe($this->packageConfig)
        ->and($queries)->toBe(0);
});

it('keeps an existing config when the overwrite is declined', function () {
    File::put(config_path('orphan-storage.php'), '<?php return ["custom" => true];');

    $this->artisan('orphan:install')
        ->expectsConfirmation($this->overwriteQuestion, 'no')
        ->expectsOutputToContain('Kept your existing config/orphan-storage.php.')
        ->assertSuccessful();

    expect(file_get_contents(config_path('orphan-storage.php')))->toBe('<?php return ["custom" => true];');
});

it('overwrites an existing config when the overwrite is confirmed', function () {
    File::put(config_path('orphan-storage.php'), '<?php return ["custom" => true];');

    $this->artisan('orphan:install')
        ->expectsConfirmation($this->overwriteQuestion, 'yes')
        ->expectsOutputToContain('Published config/orphan-storage.php.')
        ->assertSuccessful();

    expect(file_get_contents(config_path('orphan-storage.php')))->toBe($this->packageConfig);
});

it('overwrites an existing config without asking when forced', function () {
    File::put(config_path('orphan-storage.php'), '<?php return ["custom" => true];');

    $this->artisan('orphan:install', ['--force' => true])
        ->doesntExpectOutputToContain($this->overwriteQuestion)
        ->expectsOutputToContain('Published config/orphan-storage.php.')
        ->assertSuccessful();

    expect(file_get_contents(config_path('orphan-storage.php')))->toBe($this->packageConfig);
});
