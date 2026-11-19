<?php

namespace Erag\OrphanFiles\Commands;

use Erag\OrphanFiles\Commands\Concerns\InteractsWithOrphanOptions;
use Erag\OrphanFiles\Exceptions\SafetyThresholdExceeded;
use Erag\OrphanFiles\OrphanFiles;
use Erag\OrphanFiles\Scanner\FileInfo;
use Erag\OrphanFiles\Support\Bytes;
use Erag\OrphanFiles\Support\Data\CleanOptions;
use Erag\OrphanFiles\Support\Data\CleanResult;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class CleanCommand extends Command
{
    use InteractsWithOrphanOptions;

    protected const SAMPLE_SIZE = 15;

    protected $signature = 'orphan:clean
        {--disk=* : Only clean these disks}
        {--dir=* : Only clean these directories}
        {--mode= : "trash" (restorable) or "delete" (permanent)}
        {--older-than= : Replace the grace period, e.g. 90m, 48h or 7d}
        {--limit= : Clean at most this many files}
        {--force : Trash or delete the files. Without it the command is a dry run}
        {--force-ratio : Ignore the safety ratio threshold}
        {--queue : Run the clean on the queue (requires --force)}';

    protected $description = 'Trash or delete orphaned storage files (dry run unless --force).';

    public function handle(OrphanFiles $orphanFiles): int
    {
        $samples = [];
        $verbose = $this->output->isVerbose();

        try {
            $options = new CleanOptions(
                scan: $this->scanOptions(),
                mode: $orphanFiles->mode($this->stringOption('mode')),
                limit: $this->integerOption('limit'),
                dryRun: ! $this->option('force'),
                forceRatio: (bool) $this->option('force-ratio'),
            );

            if ($this->option('queue')) {
                if ($options->dryRun) {
                    throw new InvalidArgumentException('--queue needs --force. Preview the clean without --queue first.');
                }

                $orphanFiles->queueClean($options);
                $this->components->info('The clean was queued.');

                return self::SUCCESS;
            }

            $result = $orphanFiles->clean($options, function (FileInfo $file, ?string $reasonToKeep) use (&$samples, $verbose, $options): void {
                if ($reasonToKeep !== null || ! $options->dryRun) {
                    return;
                }

                // Verbose dry runs stream every file instead of buffering millions of them.
                if ($verbose) {
                    $this->components->twoColumnDetail("{$file->disk}:{$file->path}", Bytes::format($file->size));
                } elseif (count($samples) < self::SAMPLE_SIZE) {
                    $samples[] = $file;
                }
            });
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->renderReport($result->report);

        return $result->dryRun ? $this->renderDryRun($result, $samples) : $this->renderResult($result);
    }

    /**
     * @param  list<FileInfo>  $samples
     */
    protected function renderDryRun(CleanResult $result, array $samples): int
    {
        $candidates = $result->candidates();

        if ($candidates === 0) {
            $this->components->info('Nothing to clean.');

            return self::SUCCESS;
        }

        $samples = array_slice($samples, 0, $candidates);

        foreach ($samples as $file) {
            $this->components->twoColumnDetail("{$file->disk}:{$file->path}", Bytes::format($file->size));
        }

        if (! $this->output->isVerbose() && $candidates > count($samples)) {
            $this->line(sprintf('  <fg=gray>… and %s more (use -v to list every file).</>', number_format($candidates - count($samples))));
        }

        if ($result->ratioViolations !== []) {
            $this->components->warn((new SafetyThresholdExceeded($result->ratioViolations, (float) config('orphan-files.max_orphan_ratio', 90)))->getMessage());
        }

        $this->components->info(sprintf(
            'Dry run: nothing was changed. Re-run with --force to %s %s %s.',
            $result->mode,
            number_format($candidates),
            Str::plural('file', $candidates),
        ));

        return self::SUCCESS;
    }

    protected function renderResult(CleanResult $result): int
    {
        $action = $result->action;

        if ($action === null || ($action->processed === 0 && $action->failed === 0)) {
            $this->components->info('Nothing to clean.');

            return self::SUCCESS;
        }

        $this->components->info(sprintf(
            '%s %s %s (%s).%s',
            $result->mode === 'delete' ? 'Deleted' : 'Moved to the trash:',
            number_format($action->processed),
            Str::plural('file', $action->processed),
            Bytes::format($action->bytes),
            $result->mode === 'trash' ? ' Restore them with php artisan orphan:restore.' : '',
        ));

        if ($action->failed === 0) {
            return self::SUCCESS;
        }

        $this->components->error(sprintf('%s %s could not be cleaned:', number_format($action->failed), Str::plural('file', $action->failed)));

        $this->table(['Disk', 'Path', 'Error'], array_map(
            fn (array $failure): array => [$failure['disk'], $failure['path'], $failure['error']],
            array_slice($action->failures, 0, 50),
        ));

        return self::FAILURE;
    }
}
