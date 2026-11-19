<?php

namespace Erag\OrphanFiles\Commands;

use Erag\OrphanFiles\Commands\Concerns\InteractsWithOrphanOptions;
use Erag\OrphanFiles\OrphanFiles;
use Erag\OrphanFiles\Support\Bytes;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PurgeTrashCommand extends Command
{
    use InteractsWithOrphanOptions;

    protected $signature = 'orphan:purge-trash
        {--disk= : Only purge the trash of this disk}
        {--days= : Keep trash from the last this many days, 0 keeps none (defaults to trash.retention_days)}
        {--all : Purge every trashed file, whatever its age}
        {--dry-run : Show what would be purged without deleting}';

    protected $description = 'Permanently delete trashed orphan files older than the retention period.';

    public function handle(OrphanFiles $orphanFiles): int
    {
        try {
            $result = $orphanFiles->purgeTrash(
                $this->stringOption('disk'),
                $this->integerOption('days'),
                (bool) $this->option('all'),
                (bool) $this->option('dry-run'),
            );
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->components->info(sprintf(
                'Dry run: %s trashed %s (%s) would be purged.',
                number_format($result->processed),
                Str::plural('file', $result->processed),
                Bytes::format($result->bytes),
            ));

            return self::SUCCESS;
        }

        $this->components->info(sprintf('Purged %s trashed %s (%s).', number_format($result->processed), Str::plural('file', $result->processed), Bytes::format($result->bytes)));

        foreach ($result->failures as $failure) {
            $this->components->twoColumnDetail("{$failure['disk']}:{$failure['path']}", "<fg=red>{$failure['error']}</>");
        }

        return $result->failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
