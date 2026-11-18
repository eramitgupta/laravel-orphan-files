<?php

namespace Erag\OrphanStorage\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Publishes config/orphan-storage.php and prints what to do next.
 * It never touches the database and creates no migrations.
 */
class InstallCommand extends Command
{
    protected $signature = 'orphan:install
        {--force : Overwrite an existing config/orphan-storage.php without asking}';

    protected $description = 'Publish the orphan-storage config file and show the next steps.';

    public function handle(Filesystem $files): int
    {
        $target = config_path('orphan-storage.php');

        if ($this->shouldWriteConfig($files, $target)) {
            $files->ensureDirectoryExists(dirname($target));
            $files->copy(dirname(__DIR__, 2).'/config/orphan-storage.php', $target);

            $this->components->info('Published config/orphan-storage.php.');
        } else {
            $this->components->info('Kept your existing config/orphan-storage.php.');
        }

        $this->showNextSteps();

        return self::SUCCESS;
    }

    protected function shouldWriteConfig(Filesystem $files, string $target): bool
    {
        if (! $files->exists($target) || $this->option('force')) {
            return true;
        }

        return $this->components->confirm('config/orphan-storage.php already exists. Overwrite it with the package defaults?', false);
    }

    protected function showNextSteps(): void
    {
        $this->line('  <options=bold>Next steps</>');
        $this->line("  1. In config/orphan-storage.php, list your upload folders under 'disks' and the columns that store file paths under 'sources'.");
        $this->line('  2. Run <fg=green>php artisan orphan:scan</> to see how many orphaned files you have.');
        $this->line('  3. Run <fg=green>php artisan orphan:clean</> for a dry run, then <fg=green>php artisan orphan:clean --force</> to move the orphans to the trash.');
        $this->newLine();
        $this->line('  Docs: https://erag.in/laravel-orphan-storage/');
        $this->newLine();
    }
}
