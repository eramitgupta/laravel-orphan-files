<?php

namespace Erag\OrphanFiles\Actions;

use Erag\OrphanFiles\Events\OrphanFilesDeleted;
use Erag\OrphanFiles\Scanner\FileInfo;
use Erag\OrphanFiles\Support\Chunks;
use Erag\OrphanFiles\Support\Data\ActionResult;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\AwsS3V3Adapter;
use League\Flysystem\PathPrefixer;
use Throwable;

/**
 * Deletes files permanently. S3 and S3-compatible disks use DeleteObjects
 * (up to 1,000 keys per request) instead of one request per file.
 */
class DeleteFiles
{
    protected const S3_BATCH_SIZE = 1000;

    public function __construct(
        protected FilesystemFactory $filesystems,
        protected Dispatcher $events,
        protected Repository $config,
    ) {}

    /**
     * @param  iterable<int, FileInfo>  $files
     */
    public function handle(iterable $files): ActionResult
    {
        $result = new ActionResult('delete');

        foreach (Chunks::of($files, (int) $this->config->get('orphan-files.chunk_size', 1000)) as $chunk) {
            $byDisk = [];

            foreach ($chunk as $file) {
                $byDisk[$file->disk][] = $file;
            }

            foreach ($byDisk as $disk => $batch) {
                $deleted = $this->deleteBatch((string) $disk, $batch, $result);

                if ($deleted !== []) {
                    $this->events->dispatch(new OrphanFilesDeleted(
                        (string) $disk,
                        array_map(fn (FileInfo $file): string => $file->path, $deleted),
                        array_sum(array_map(fn (FileInfo $file): int => (int) $file->size, $deleted)),
                    ));
                }
            }
        }

        return $result;
    }

    /**
     * @param  list<FileInfo>  $files
     * @return list<FileInfo> The files that were deleted.
     */
    protected function deleteBatch(string $disk, array $files, ActionResult $result): array
    {
        $filesystem = $this->filesystems->disk($disk);
        $errors = $filesystem instanceof AwsS3V3Adapter ? $this->deleteFromS3($filesystem, $disk, $files) : null;

        if ($errors === null) {
            $errors = $this->deleteOneByOne($filesystem, $files);
        }

        $deleted = [];

        foreach ($files as $file) {
            if (isset($errors[$file->path])) {
                $result->failed($disk, $file->path, $errors[$file->path]);

                continue;
            }

            $result->succeeded($disk, $file->path, $file->size);
            $deleted[] = $file;
        }

        return $deleted;
    }

    /**
     * @param  list<FileInfo>  $files
     * @return array<string, string> Errors keyed by path.
     */
    protected function deleteOneByOne(Filesystem $filesystem, array $files): array
    {
        $errors = [];

        foreach ($files as $file) {
            try {
                if (! $filesystem->delete($file->path)) {
                    $errors[$file->path] = 'Unable to delete the file.';
                }
            } catch (Throwable $exception) {
                $errors[$file->path] = $exception->getMessage();
            }
        }

        return $errors;
    }

    /**
     * @param  list<FileInfo>  $files
     * @return array<string, string>|null Errors keyed by path, or null when batch deletes are unavailable.
     */
    protected function deleteFromS3(AwsS3V3Adapter $filesystem, string $disk, array $files): ?array
    {
        $config = $filesystem->getConfig();

        // Read-only disks and disks without a bucket go through the normal adapter, which refuses or handles them.
        if (! empty($config['read-only']) || ! is_string($config['bucket'] ?? null) || $config['bucket'] === '') {
            return null;
        }

        $prefixer = new PathPrefixer((string) ($config['root'] ?? ''), '/');

        if (isset($config['prefix'])) {
            $prefixer = new PathPrefixer($prefixer->prefixPath((string) $config['prefix']), '/');
        }

        $errors = [];

        try {
            foreach (array_chunk($files, self::S3_BATCH_SIZE) as $batch) {
                $paths = [];

                foreach ($batch as $file) {
                    $paths[$prefixer->prefixPath($file->path)] = $file->path;
                }

                $response = $filesystem->getClient()->deleteObjects([
                    'Bucket' => $config['bucket'],
                    'Delete' => [
                        'Objects' => array_map(fn (int|string $key): array => ['Key' => (string) $key], array_keys($paths)),
                        'Quiet' => true,
                    ],
                ]);

                foreach ((array) ($response['Errors'] ?? []) as $error) {
                    $path = $paths[$error['Key'] ?? ''] ?? null;

                    if ($path !== null) {
                        $errors[$path] = (string) ($error['Message'] ?? $error['Code'] ?? 'Unable to delete the object.');
                    }
                }
            }
        } catch (Throwable) {
            // Fall back to single deletes, which are idempotent for objects already removed.
            return null;
        }

        return $errors;
    }
}
