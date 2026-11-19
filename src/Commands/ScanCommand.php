<?php

namespace Erag\OrphanFiles\Commands;

use Erag\OrphanFiles\Commands\Concerns\InteractsWithOrphanOptions;
use Erag\OrphanFiles\OrphanFiles;
use Erag\OrphanFiles\Scanner\FileInfo;
use Erag\OrphanFiles\Support\Bytes;
use Erag\OrphanFiles\Support\CsvWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class ScanCommand extends Command
{
    use InteractsWithOrphanOptions;

    protected $signature = 'orphan:scan
        {--disk=* : Only scan these disks}
        {--dir=* : Only scan these directories}
        {--older-than= : Replace the grace period, e.g. 90m, 48h or 7d}
        {--json : Print the summary as JSON}
        {--export= : Export the orphans to a file (csv)}
        {--output= : Where to write the export (defaults to export_path)}
        {--queue : Run the scan on the queue}';

    protected $description = 'Find storage files that no database row references.';

    public function handle(OrphanFiles $orphanFiles): int
    {
        $writer = null;

        try {
            $options = $this->scanOptions();

            if ($this->option('queue')) {
                $orphanFiles->queueScan($options);
                $this->components->info('The scan was queued. Listen for the OrphanScanCompleted event to read the report.');

                return self::SUCCESS;
            }

            $writer = $this->exportWriter();
            $verbose = $this->output->isVerbose() && ! $this->option('json');

            $report = $orphanFiles->scan($options, function (FileInfo $file, ?string $reasonToKeep) use ($writer, $verbose): void {
                $writer?->write($file, $reasonToKeep);

                if ($verbose) {
                    $this->line(sprintf('  %s:%s <fg=gray>%s</>', $file->disk, $file->path, $reasonToKeep ?? 'cleanable'));
                }
            });
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            $writer?->close();
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->renderReport($report);

        $totals = $report->totals();

        if ($writer !== null) {
            $this->components->info(sprintf('Exported %s %s to %s', number_format($writer->rows), Str::plural('orphan', $writer->rows), $writer->path));
        }

        if ($totals['cleanable'] > 0) {
            $this->components->warn(sprintf(
                '%s orphaned %s (%s) can be cleaned. Preview with: php artisan orphan:clean',
                number_format($totals['cleanable']),
                Str::plural('file', $totals['cleanable']),
                Bytes::format($totals['reclaimable_bytes']),
            ));
        } else {
            $this->components->info('No orphaned files to clean.');
        }

        return self::SUCCESS;
    }

    protected function exportWriter(): ?CsvWriter
    {
        $format = $this->stringOption('export');

        if ($format === null) {
            return null;
        }

        if (strtolower($format) !== 'csv') {
            throw new InvalidArgumentException("Unsupported export format [{$format}]. Use --export=csv.");
        }

        $output = $this->option('output');
        $path = is_string($output) && $output !== ''
            ? $output
            : rtrim((string) config('orphan-files.export_path', storage_path('orphan-files')), '/').'/orphans-'.Date::now()->format('Y-m-d_His').'.csv';

        return new CsvWriter($path);
    }
}
