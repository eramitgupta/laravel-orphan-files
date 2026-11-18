<?php

namespace Erag\OrphanFiles\Concerns;

use Erag\OrphanFiles\References\Source;
use Illuminate\Database\Eloquent\Model;

/**
 * Declares which columns of a model hold storage references:
 *
 *     protected array $storageFiles = ['image' => 'path', 'gallery' => 'json'];
 *
 * Models using this trait are discovered automatically and registered as
 * sources for scans. The trait only declares columns: it listens to no
 * model events and never changes or deletes anything.
 *
 * @mixin Model
 */
trait HasStorageFiles
{
    /**
     * The storage columns of the model, e.g. ['image' => 'path', 'gallery' => ['type' => 'json', 'key' => '*.path']].
     *
     * @return array<string, string|array<string, mixed>>
     */
    public function getStorageFiles(): array
    {
        return property_exists($this, 'storageFiles') && is_array($this->storageFiles) ? $this->storageFiles : [];
    }

    /**
     * The default disk for this model's files, or null to match every scanned disk.
     */
    public function getStorageDisk(): ?string
    {
        return property_exists($this, 'storageDisk') && is_string($this->storageDisk) ? $this->storageDisk : null;
    }

    /**
     * @return list<Source>
     */
    public function storageFileSources(): array
    {
        $sources = [];

        foreach ($this->getStorageFiles() as $column => $definition) {
            $definition = is_string($definition) ? ['type' => $definition] : (array) $definition;

            $sources[] = Source::fromArray([
                ...$definition,
                'model' => static::class,
                'column' => $column,
                'disk' => $definition['disk'] ?? $this->getStorageDisk(),
            ], static::class.'::'.$column);
        }

        return $sources;
    }
}
