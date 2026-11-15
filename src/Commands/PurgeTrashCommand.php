<?php

namespace Erag\OrphanStorage\Commands;

use Erag\OrphanStorage\Commands\Concerns\InteractsWithOrphanOptions;
use Erag\OrphanStorage\OrphanStorage;
use Erag\OrphanStorage\Support\Bytes;
use Illuminate\Console\Command;
use InvalidArgumentException;

class PurgeTrashCommand extends Command
{
    use InteractsWithOrphanOptions;

    protected $signature = 'orphan:purge-trash
        {--disk= : Only purge the trash of this disk}
        {--days= : Keep trash younger than this many days (defaults to trash.retention_days)}
        {--all : Purge every trashed file, whatever its age}
        {--dry-run : Show what would be purged without deleting}';

    protected $description = 'Permanently delete trashed orphan files older than the retention period.';

    public function handle(OrphanStorage $orphanStorage): int
    {
        try {
            $result = $orphanStorage->purgeTrash(
                $this->option('disk') ?: null,
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
                'Dry run: %s trashed files (%s) would be purged.',
                number_format($result->processed),
                Bytes::format($result->bytes),
            ));

            return self::SUCCESS;
        }

        $this->components->info(sprintf('Purged %s trashed files (%s).', number_format($result->processed), Bytes::format($result->bytes)));

        foreach ($result->failures as $failure) {
            $this->components->twoColumnDetail("{$failure['disk']}:{$failure['path']}", "<fg=red>{$failure['error']}</>");
        }

        return $result->failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
