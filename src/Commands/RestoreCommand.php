<?php

namespace Erag\OrphanFiles\Commands;

use Erag\OrphanFiles\Commands\Concerns\InteractsWithOrphanOptions;
use Erag\OrphanFiles\OrphanFiles;
use Erag\OrphanFiles\Support\Bytes;
use Illuminate\Console\Command;
use InvalidArgumentException;

class RestoreCommand extends Command
{
    use InteractsWithOrphanOptions;

    protected const LIST_LIMIT = 500;

    protected $signature = 'orphan:restore
        {paths?* : Original paths (files or directories) to restore}
        {--disk= : The disk the files were trashed from}
        {--date= : Only restore copies trashed on this day (Y-m-d)}
        {--all : Restore everything in the trash of the disk}
        {--overwrite : Replace files that already exist at the original path}
        {--list : List trashed files instead of restoring}';

    protected $description = 'Restore trashed orphan files to their original paths.';

    public function handle(OrphanFiles $orphanFiles): int
    {
        $disk = (string) ($this->option('disk') ?: $orphanFiles->defaultDisk());
        $date = $this->option('date') ?: null;
        $paths = array_values(array_filter((array) $this->argument('paths'), 'is_string'));

        if ($date !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date) !== 1) {
            $this->components->error('The --date option must look like 2026-01-31.');

            return self::FAILURE;
        }

        if ($this->option('list')) {
            return $this->listTrash($orphanFiles, $disk, $date, $paths[0] ?? '');
        }

        if ($paths === [] && ! $this->option('all')) {
            $this->components->error('Give the paths to restore, or pass --all. Use --list to see the trash.');

            return self::FAILURE;
        }

        try {
            $result = $this->option('all')
                ? $orphanFiles->restoreAll($disk, $date, (bool) $this->option('overwrite'))
                : $orphanFiles->restore($disk, $paths, $date, (bool) $this->option('overwrite'));
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($result->files as $file) {
            $this->components->twoColumnDetail("{$file['disk']}:{$file['path']}", '<fg=green>RESTORED</>');
        }

        foreach ($result->failures as $failure) {
            $this->components->twoColumnDetail("{$failure['disk']}:{$failure['path']}", "<fg=red>{$failure['error']}</>");
        }

        $this->components->info(sprintf('Restored %s files (%s).', number_format($result->processed), Bytes::format($result->bytes)));

        return $result->failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    protected function listTrash(OrphanFiles $orphanFiles, string $disk, ?string $date, string $prefix): int
    {
        $rows = [];
        $count = 0;

        foreach ($orphanFiles->trashed($disk, $date, $prefix) as $file) {
            $count++;

            if (count($rows) < self::LIST_LIMIT) {
                $rows[] = [$file->bucket, $file->path, Bytes::format($file->size)];
            }
        }

        if ($count === 0) {
            $this->components->info("The trash of disk [{$disk}] is empty.");

            return self::SUCCESS;
        }

        $this->table(['Trashed on', 'Original path', 'Size'], $rows);

        if ($count > count($rows)) {
            $this->line(sprintf('  <fg=gray>… and %s more.</>', number_format($count - count($rows))));
        }

        return self::SUCCESS;
    }
}
