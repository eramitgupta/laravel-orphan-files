<?php

namespace Erag\OrphanFiles\References;

use Erag\OrphanFiles\Exceptions\InvalidSourceException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\DatabaseManager;

/**
 * Every place in the database that may reference a stored file: config
 * sources, discovered HasStorageFiles models and runtime registrations.
 */
class SourceRegistry
{
    /**
     * @var list<Source>
     */
    protected array $registered = [];

    public function __construct(
        protected Repository $config,
        protected ModelDiscovery $discovery,
        protected DatabaseManager $db,
        protected ReferenceCollector $collector,
    ) {}

    /**
     * Register a source at runtime.
     *
     * @param  Source|array<string, mixed>  $source
     */
    public function add(Source|array $source): void
    {
        $this->registered[] = $source instanceof Source ? $source : Source::fromArray($source, 'runtime');
    }

    /**
     * @return list<Source>
     *
     * @throws InvalidSourceException
     */
    public function all(): array
    {
        $sources = [];

        foreach ((array) $this->config->get('orphan-files.sources', []) as $index => $definition) {
            if (! is_array($definition)) {
                throw InvalidSourceException::invalidDefinition($index, 'each source must be an array.');
            }

            $sources[] = Source::fromArray($definition, $index);
        }

        array_push($sources, ...$this->discovery->sources(), ...$this->registered);

        $unique = [];

        foreach ($sources as $source) {
            $unique[$source->signature()] ??= $source;
        }

        return array_values($unique);
    }

    /**
     * Make sure every table, column and type exists before anything is read.
     *
     * @param  list<Source>  $sources
     *
     * @throws InvalidSourceException
     */
    public function validate(array $sources): void
    {
        foreach ($sources as $source) {
            $this->collector->extractorFor($source);

            if ($source->primaryKey === null) {
                throw InvalidSourceException::invalidDefinition($source->label(), 'a unique "primary_key" is required to read the table in chunks.');
            }

            $schema = $this->db->connection($source->connection)->getSchemaBuilder();

            if (! $schema->hasTable($source->table)) {
                throw InvalidSourceException::tableNotFound($source);
            }

            $columns = array_filter([$source->column, $source->primaryKey]);

            foreach (array_unique($columns) as $column) {
                if (! $schema->hasColumn($source->table, $column)) {
                    throw InvalidSourceException::columnNotFound($source, $column);
                }
            }
        }
    }
}
