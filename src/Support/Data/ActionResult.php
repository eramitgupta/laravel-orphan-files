<?php

namespace Erag\OrphanFiles\Support\Data;

final class ActionResult
{
    /**
     * How many failures and paths are kept for display. Counters are always exact.
     */
    public const MAX_TRACKED = 1000;

    public int $processed = 0;

    public int $failed = 0;

    public int $bytes = 0;

    /**
     * The first failures (up to MAX_TRACKED).
     *
     * @var list<array{disk: string, path: string, error: string}>
     */
    public array $failures = [];

    /**
     * The first processed paths (up to MAX_TRACKED), only kept when the action asks for them.
     *
     * @var list<array{disk: string, path: string}>
     */
    public array $files = [];

    /**
     * @param  string  $action  "trash", "delete", "restore" or "purge".
     */
    public function __construct(
        public readonly string $action,
        protected bool $keepPaths = false,
    ) {}

    public function succeeded(string $disk, string $path, ?int $size = null): void
    {
        $this->processed++;
        $this->bytes += (int) $size;

        if ($this->keepPaths && count($this->files) < self::MAX_TRACKED) {
            $this->files[] = ['disk' => $disk, 'path' => $path];
        }
    }

    public function failed(string $disk, string $path, string $error): void
    {
        $this->failed++;

        if (count($this->failures) < self::MAX_TRACKED) {
            $this->failures[] = ['disk' => $disk, 'path' => $path, 'error' => $error];
        }
    }

    public function merge(self $other): self
    {
        $this->processed += $other->processed;
        $this->failed += $other->failed;
        $this->bytes += $other->bytes;
        $this->failures = array_slice([...$this->failures, ...$other->failures], 0, self::MAX_TRACKED);
        $this->files = array_slice([...$this->files, ...$other->files], 0, self::MAX_TRACKED);

        return $this;
    }
}
