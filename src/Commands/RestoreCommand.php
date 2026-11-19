<?php

namespace Erag\OrphanFiles\Commands;

use Erag\OrphanFiles\Commands\Concerns\InteractsWithOrphanOptions;
use Erag\OrphanFiles\OrphanFiles;
use Erag\OrphanFiles\Support\Bytes;
use Erag\OrphanFiles\Support\Data\ActionResult;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RestoreCommand extends Command
{
    use InteractsWithOrphanOptions;

    protected const LIST_LIMIT = 500;

    protected $signature = 'orphan:restore
        {paths?* : Original paths (files or directories) to restore}
        {--disk= : Only use the trash of this disk (defaults to every configured disk)}
        {--date= : Only restore copies trashed on this day (Y-m-d)}
        {--all : Restore everything in the trash}
        {--overwrite : Replace files that already exist at the original path}
        {--list : List trashed files instead of restoring}';

    protected $description = 'Restore trashed orphan files to their original paths.';

    public function handle(OrphanFiles $orphanFiles): int
    {
        $date = $this->stringOption('date');
        $paths = array_values(array_filter((array) $this->argument('paths'), 'is_string'));
        $disk = $this->stringOption('disk');
        $disks = $disk !== null ? [$disk] : $orphanFiles->trashDisks();

        if ($date !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            $this->components->error('The --date option must look like 2026-01-31.');

            return self::FAILURE;
        }

        if ($this->option('list')) {
            return $this->listTrash($orphanFiles, $disks, $date, $paths[0] ?? '');
        }

        if ($paths === [] && ! $this->option('all')) {
            $this->components->error('Give the paths to restore, or pass --all. Use --list to see the trash.');

            return self::FAILURE;
        }

        try {
            $result = $this->option('all')
                ? $this->restoreAll($orphanFiles, $disks, $date)
                : $this->restorePaths($orphanFiles, $disks, $paths, $date);
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($result->files as $file) {
            $this->components->twoColumnDetail("{$file['disk']}:{$file['path']}", '<fg=green>RESTORED</>');
        }

        foreach ($result->failures as $failure) {
            $label = $failure['disk'] === '' ? $failure['path'] : "{$failure['disk']}:{$failure['path']}";

            $this->components->twoColumnDetail($label, "<fg=red>{$failure['error']}</>");
        }

        return $this->renderSummary($result);
    }

    /**
     * @param  list<string>  $disks
     */
    protected function restoreAll(OrphanFiles $orphanFiles, array $disks, ?string $date): ActionResult
    {
        $result = new ActionResult('restore', keepPaths: true);

        foreach ($disks as $disk) {
            $result->merge($orphanFiles->restoreAll($disk, $date, (bool) $this->option('overwrite')));
        }

        return $result;
    }

    /**
     * Restore each path from the trash of the disk that holds it. With one
     * disk the action reports missing paths itself; with several, a path is
     * reported once when no disk's trash has it.
     *
     * @param  list<string>  $disks
     * @param  list<string>  $paths
     */
    protected function restorePaths(OrphanFiles $orphanFiles, array $disks, array $paths, ?string $date): ActionResult
    {
        $overwrite = (bool) $this->option('overwrite');

        if (count($disks) === 1) {
            return $orphanFiles->restore($disks[0], $paths, $date, $overwrite);
        }

        $result = new ActionResult('restore', keepPaths: true);

        foreach ($paths as $path) {
            $holders = array_values(array_filter($disks, fn (string $disk): bool => $orphanFiles->inTrash($disk, $path, $date)));

            if ($holders === []) {
                $result->failed('', $path, 'Not found in the trash of '.$this->listDisks($disks).'.');

                continue;
            }

            foreach ($holders as $disk) {
                $result->merge($orphanFiles->restore($disk, [$path], $date, $overwrite));
            }
        }

        return $result;
    }

    protected function renderSummary(ActionResult $result): int
    {
        $restored = sprintf(
            'Restored %s %s (%s).',
            number_format($result->processed),
            Str::plural('file', $result->processed),
            Bytes::format($result->bytes),
        );

        if ($result->failed === 0) {
            $this->components->info($restored);

            return self::SUCCESS;
        }

        $failed = sprintf('%s %s could not be restored, see above.', number_format($result->failed), Str::plural('file', $result->failed));

        $this->components->warn($result->processed > 0 ? "{$restored} {$failed}" : $failed);

        return self::FAILURE;
    }

    /**
     * @param  list<string>  $disks
     */
    protected function listTrash(OrphanFiles $orphanFiles, array $disks, ?string $date, string $prefix): int
    {
        $rows = [];
        $count = 0;
        $withDisk = count($disks) > 1;

        foreach ($disks as $disk) {
            foreach ($orphanFiles->trashed($disk, $date, $prefix) as $file) {
                $count++;

                if (count($rows) < self::LIST_LIMIT) {
                    $row = [$file->bucket, $file->path, Bytes::format($file->size)];
                    $rows[] = $withDisk ? [$disk, ...$row] : $row;
                }
            }
        }

        if ($count === 0) {
            $this->components->info($withDisk
                ? 'The trash is empty on '.$this->listDisks($disks, 'and').'.'
                : "The trash of disk [{$disks[0]}] is empty.");

            return self::SUCCESS;
        }

        $headers = ['Trashed on', 'Original path', 'Size'];
        $this->table($withDisk ? ['Disk', ...$headers] : $headers, $rows);

        if ($count > count($rows)) {
            $this->line(sprintf('  <fg=gray>… and %s more.</>', number_format($count - count($rows))));
        }

        return self::SUCCESS;
    }

    /**
     * "public", "public or s3", "local, public or s3" (or "and").
     *
     * @param  list<string>  $disks
     */
    protected function listDisks(array $disks, string $conjunction = 'or'): string
    {
        if (count($disks) <= 1) {
            return $disks[0] ?? 'any disk';
        }

        return implode(', ', array_slice($disks, 0, -1))." {$conjunction} ".end($disks);
    }
}
