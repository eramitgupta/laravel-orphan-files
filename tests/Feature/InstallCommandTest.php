<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->configDirectory = sys_get_temp_dir().'/orphan-files-install-'.uniqid();
    File::ensureDirectoryExists($this->configDirectory);
    $this->app->useConfigPath($this->configDirectory);

    $this->packageConfig = (string) file_get_contents(dirname(__DIR__, 2).'/config/orphan-files.php');
    $this->overwriteQuestion = 'config/orphan-files.php already exists. Overwrite it with the package defaults?';
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
        ->expectsOutputToContain('Published config/orphan-files.php.')
        ->expectsOutputToContain('php artisan orphan:scan')
        ->expectsOutputToContain('php artisan orphan:clean --force')
        ->assertSuccessful();

    expect(file_get_contents(config_path('orphan-files.php')))->toBe($this->packageConfig)
        ->and($queries)->toBe(0);
});

it('keeps an existing config when the overwrite is declined', function () {
    File::put(config_path('orphan-files.php'), '<?php return ["custom" => true];');

    $this->artisan('orphan:install')
        ->expectsConfirmation($this->overwriteQuestion, 'no')
        ->expectsOutputToContain('Kept your existing config/orphan-files.php.')
        ->assertSuccessful();

    expect(file_get_contents(config_path('orphan-files.php')))->toBe('<?php return ["custom" => true];');
});

it('overwrites an existing config when the overwrite is confirmed', function () {
    File::put(config_path('orphan-files.php'), '<?php return ["custom" => true];');

    $this->artisan('orphan:install')
        ->expectsConfirmation($this->overwriteQuestion, 'yes')
        ->expectsOutputToContain('Published config/orphan-files.php.')
        ->assertSuccessful();

    expect(file_get_contents(config_path('orphan-files.php')))->toBe($this->packageConfig);
});

it('overwrites an existing config without asking when forced', function () {
    File::put(config_path('orphan-files.php'), '<?php return ["custom" => true];');

    $this->artisan('orphan:install', ['--force' => true])
        ->doesntExpectOutputToContain($this->overwriteQuestion)
        ->expectsOutputToContain('Published config/orphan-files.php.')
        ->assertSuccessful();

    expect(file_get_contents(config_path('orphan-files.php')))->toBe($this->packageConfig);
});
