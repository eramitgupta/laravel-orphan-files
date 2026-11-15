<?php

namespace Erag\OrphanStorage\References;

use Erag\OrphanStorage\Exceptions\InvalidSourceException;
use Illuminate\Database\Eloquent\Model;

final class Source
{
    /**
     * @param  string|null  $column  Null when the extractor receives the whole row.
     * @param  string|null  $primaryKey  A unique, orderable column used to read the table in chunks.
     * @param  array<string, mixed>  $options  Extra settings for the extractor, such as the JSON `key`.
     */
    public function __construct(
        public readonly string $table,
        public readonly ?string $column,
        public readonly string $type = 'path',
        public readonly ?string $disk = null,
        public readonly ?string $connection = null,
        public readonly ?string $primaryKey = 'id',
        public readonly ?string $model = null,
        public readonly array $options = [],
    ) {}

    /**
     * Build a source from a config entry.
     *
     * @param  array<string, mixed>  $definition
     *
     * @throws InvalidSourceException
     */
    public static function fromArray(array $definition, int|string $index = 0): self
    {
        $type = $definition['type'] ?? 'path';

        if (! is_string($type) || $type === '') {
            throw InvalidSourceException::invalidDefinition($index, '"type" must be a non-empty string.');
        }

        $table = $definition['table'] ?? null;
        $connection = $definition['connection'] ?? null;
        $primaryKey = array_key_exists('primary_key', $definition) ? $definition['primary_key'] : 'id';
        $model = $definition['model'] ?? null;

        if ($model !== null) {
            if (! is_string($model) || ! class_exists($model) || ! is_subclass_of($model, Model::class)) {
                throw InvalidSourceException::invalidDefinition($index, '"model" must be an Eloquent model class.');
            }

            /** @var Model $instance */
            $instance = new $model;

            $table ??= $instance->getTable();
            $connection ??= $instance->getConnectionName();
            $primaryKey = array_key_exists('primary_key', $definition) ? $definition['primary_key'] : $instance->getKeyName();
        }

        if (! is_string($table) || $table === '') {
            throw InvalidSourceException::invalidDefinition($index, 'a "table" or "model" is required.');
        }

        $column = $definition['column'] ?? null;

        if (! is_string($column) || $column === '') {
            throw InvalidSourceException::invalidDefinition($index, 'a "column" is required (use "*" to pass whole rows to a custom extractor).');
        }

        $disk = $definition['disk'] ?? null;

        return new self(
            table: $table,
            column: $column === '*' ? null : $column,
            type: $type,
            disk: is_string($disk) && $disk !== '' ? $disk : null,
            connection: is_string($connection) && $connection !== '' ? $connection : null,
            primaryKey: is_string($primaryKey) && $primaryKey !== '' ? $primaryKey : null,
            model: $model,
            options: array_diff_key($definition, array_flip(['table', 'model', 'column', 'type', 'disk', 'connection', 'primary_key'])),
        );
    }

    /**
     * A short human readable name, e.g. "posts.body".
     */
    public function label(): string
    {
        return $this->table.'.'.($this->column ?? '*');
    }

    /**
     * A value that is equal for duplicate definitions.
     */
    public function signature(): string
    {
        return implode('|', [
            $this->connection ?? '',
            $this->table,
            $this->column ?? '*',
            $this->type,
            $this->disk ?? '',
            json_encode($this->options) ?: '',
        ]);
    }
}
