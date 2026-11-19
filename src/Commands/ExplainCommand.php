<?php

namespace Erag\OrphanFiles\Commands;

use Erag\OrphanFiles\Commands\Concerns\InteractsWithOrphanOptions;
use Erag\OrphanFiles\OrphanFiles;
use Erag\OrphanFiles\Support\Bytes;
use Erag\OrphanFiles\Support\Data\Explanation;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ExplainCommand extends Command implements PromptsForMissingInput
{
    use InteractsWithOrphanOptions;

    protected $signature = 'orphan:explain
        {path : The file path (or URL) to explain}
        {--disk= : The disk of the file (defaults to the first configured disk)}';

    protected $description = 'Show whether a file is referenced, and where, or why it counts as an orphan.';

    /**
     * Ask for the path when the command runs without one.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'path' => ['Which file do you want explained?', 'E.g. uploads/photo.jpg or a full URL'],
        ];
    }

    public function handle(OrphanFiles $orphanFiles): int
    {
        try {
            $path = $this->argument('path');
            $explanation = $orphanFiles->explain(is_string($path) ? $path : '', $this->stringOption('disk'));
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $file = $explanation->file;

        $this->components->twoColumnDetail('File', "{$explanation->path} <fg=gray>on [{$explanation->disk}]</>");
        $this->components->twoColumnDetail('Exists', $file === null ? 'no' : sprintf(
            'yes, %s%s',
            Bytes::format($file->size),
            $file->lastModified !== null ? ', modified '.Date::createFromTimestamp($file->lastModified)->diffForHumans() : '',
        ));
        $this->components->twoColumnDetail('Scanned directories', $explanation->directories === []
            ? 'disk not listed under "disks"'
            : implode(', ', array_map(fn (string $directory): string => $directory === '' ? '(whole disk)' : $directory, $explanation->directories)));
        $this->components->twoColumnDetail('Sources checked', (string) $explanation->sourcesChecked);

        if ($explanation->references !== []) {
            $this->table(['Source', 'Type', 'Row', 'Value'], array_map(fn (array $reference): array => [
                $reference['source'],
                $reference['type'],
                is_scalar($reference['key']) ? (string) $reference['key'] : '-',
                $reference['value'],
            ], $explanation->references));
        }

        $this->renderVerdict($explanation);

        return self::SUCCESS;
    }

    protected function renderVerdict(Explanation $explanation): void
    {
        $count = count($explanation->references);

        $rows = $count.' '.Str::plural('row', $count);

        match ($explanation->verdict()) {
            Explanation::REFERENCED => $explanation->file === null
                ? $this->components->warn("Referenced by {$rows}, but the file is missing from the disk (a broken reference).")
                : $this->components->info("Kept: referenced by {$rows}.".($count >= 25 ? ' Only the first 25 are shown.' : '')),
            Explanation::MISSING => $this->components->warn('The file does not exist on this disk, and no source references it.'),
            Explanation::PROTECTED => $explanation->excluded
                ? $this->components->info("Excluded: {$explanation->reasonToKeep}. Scans skip it and orphan:clean never touches it.")
                : $this->components->info('Not referenced, but kept: '.($explanation->reasonToKeep ?? 'it is outside the scanned directories (or in the trash)').'.'),
            default => $this->components->warn(sprintf(
                'Orphan: %s. orphan:clean --force would %s it.',
                $explanation->sourcesChecked === 1
                    ? 'the only source does not reference it'
                    : "none of the {$explanation->sourcesChecked} sources reference it",
                config('orphan-files.mode', 'trash'),
            )),
        };
    }
}
