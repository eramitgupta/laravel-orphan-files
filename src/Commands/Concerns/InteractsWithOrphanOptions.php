<?php

namespace Erag\OrphanFiles\Commands\Concerns;

use Erag\OrphanFiles\Support\Bytes;
use Erag\OrphanFiles\Support\Data\DiskReport;
use Erag\OrphanFiles\Support\Data\ScanOptions;
use Erag\OrphanFiles\Support\Data\ScanReport;
use Erag\OrphanFiles\Support\Duration;
use InvalidArgumentException;

/**
 * Option parsing and report rendering shared by the commands.
 */
trait InteractsWithOrphanOptions
{
    protected function scanOptions(): ScanOptions
    {
        $olderThan = $this->option('older-than');

        return new ScanOptions(
            disks: $this->listOption('disk'),
            directories: $this->listOption('dir'),
            minimumAgeMinutes: is_string($olderThan) && $olderThan !== '' ? Duration::toMinutes($olderThan) : null,
        );
    }

    /**
     * An array option that also accepts comma separated values.
     *
     * @return list<string>|null
     */
    protected function listOption(string $name): ?array
    {
        $values = [];

        foreach ((array) $this->option($name) as $value) {
            foreach (explode(',', (string) $value) as $item) {
                if (trim($item) !== '') {
                    $values[] = trim($item);
                }
            }
        }

        return $values === [] ? null : $values;
    }

    /**
     * A text option, or null when it is missing or empty.
     */
    protected function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function integerOption(string $name): ?int
    {
        $value = $this->option($name);

        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value) || (int) $value < 0) {
            throw new InvalidArgumentException("The --{$name} option must be a positive whole number.");
        }

        return (int) $value;
    }

    protected function renderReport(ScanReport $report): void
    {
        $rows = array_map(fn (DiskReport $disk): array => [
            $disk->disk,
            implode(', ', array_map(fn (string $directory): string => $directory === '' ? '(whole disk)' : $directory, $disk->directories)),
            number_format($disk->files),
            number_format($disk->referenced),
            number_format($disk->orphans),
            number_format($disk->protected),
            number_format($disk->cleanable),
            Bytes::format($disk->reclaimableBytes),
            $disk->orphanRatio().'%',
        ], array_values($report->disks));

        $this->table(
            ['Disk', 'Directories', 'Files', 'Referenced', 'Orphans', 'Protected', 'Cleanable', 'Reclaimable', 'Ratio'],
            $rows,
        );

        foreach ($report->disks as $disk) {
            foreach ($disk->protectedBy as $filter => $count) {
                $this->components->twoColumnDetail(
                    "<fg=gray>Protected on [{$disk->disk}] by {$filter}</>",
                    number_format($count),
                );
            }
        }
    }
}
