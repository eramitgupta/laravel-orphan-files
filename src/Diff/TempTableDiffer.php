<?php

namespace Erag\OrphanStorage\Diff;

use Erag\OrphanStorage\Exceptions\TemporaryTableUnavailable;
use Erag\OrphanStorage\Support\Chunks;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;
use Throwable;

/**
 * Keeps reference hashes in an indexed temporary table so huge reference
 * sets never have to fit in PHP memory. Storage files are then checked in
 * chunks with indexed lookups.
 *
 * The table is a real temporary table on every driver (SQL Server uses a
 * "#" table), so the database removes it when the session ends even if the
 * process is killed. It is dropped explicitly on release.
 */
class TempTableDiffer implements ReferenceIndex
{
    /**
     * Rows per INSERT and keys per IN (), safe for SQLite's and SQL Server's parameter limits.
     */
    protected const BATCH_SIZE = 500;

    protected string $table;

    protected bool $created = false;

    protected int $count = 0;

    public function __construct(protected Connection $connection)
    {
        $this->table = 'orphan_storage_refs_'.Str::lower(Str::random(16));
    }

    /**
     * @throws TemporaryTableUnavailable
     */
    public function add(iterable $hashes): void
    {
        $this->create();

        foreach (Chunks::of($hashes, self::BATCH_SIZE) as $chunk) {
            $this->count += $this->insertMissing(array_values(array_unique(array_map('strval', $chunk))));
        }
    }

    public function existing(array $hashes): array
    {
        if (! $this->created || $hashes === []) {
            return [];
        }

        $found = [];

        foreach (array_chunk($hashes, self::BATCH_SIZE) as $chunk) {
            foreach ($this->query()->whereIn('hash', $chunk)->pluck('hash') as $hash) {
                $found[(string) $hash] = true;
            }
        }

        return $found;
    }

    public function count(): int
    {
        return $this->count;
    }

    /**
     * Drop the table. A failing drop is reported, never thrown, so it cannot
     * hide the exception that caused the release.
     */
    public function release(): void
    {
        if (! $this->created) {
            return;
        }

        $this->created = false;
        $this->count = 0;

        try {
            $this->dropTable();
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * The table name as given to the schema builder (without prefix or "#").
     */
    public function table(): string
    {
        return $this->table;
    }

    /**
     * Insert the hashes that are not stored yet.
     *
     * Plain INSERTs after a lookup work on every driver, unlike INSERT IGNORE,
     * which SQL Server does not support. Only this process uses the table.
     *
     * @param  list<string>  $hashes  Unique hashes.
     */
    protected function insertMissing(array $hashes): int
    {
        $missing = array_values(array_diff($hashes, array_keys($this->existing($hashes))));

        if ($missing !== []) {
            $this->query()->insert(array_map(fn (string $hash): array => ['hash' => $hash], $missing));
        }

        return count($missing);
    }

    /**
     * @throws TemporaryTableUnavailable
     */
    protected function create(): void
    {
        if ($this->created) {
            return;
        }

        try {
            $this->createTable();
        } catch (Throwable $exception) {
            throw TemporaryTableUnavailable::onConnection((string) ($this->connection->getName() ?? 'default'), $exception);
        }

        $this->created = true;
    }

    protected function createTable(): void
    {
        // Laravel adds a SQL Server primary key with a separate ALTER TABLE that misses the "#"
        // name, so the table is created in one statement with an inline (unnamed) key instead.
        if ($this->isSqlServer()) {
            $grammar = $this->connection->getQueryGrammar();

            $this->connection->statement(sprintf(
                'create table %s (%s char(32) not null primary key)',
                $grammar->wrap($this->sqlServerName()),
                $grammar->wrap('hash'),
            ));

            return;
        }

        $this->connection->getSchemaBuilder()->create($this->table, function (Blueprint $table): void {
            $table->temporary();
            $table->char('hash', 32)->primary();
        });
    }

    protected function dropTable(): void
    {
        if ($this->isSqlServer()) {
            $this->connection->statement('drop table if exists '.$this->connection->getQueryGrammar()->wrap($this->sqlServerName()));

            return;
        }

        $this->connection->getSchemaBuilder()->dropIfExists($this->table);
    }

    /**
     * A query on the table, always on the connection (write PDO) that created it.
     */
    protected function query(): Builder
    {
        $table = $this->isSqlServer()
            ? new Expression($this->connection->getQueryGrammar()->wrap($this->sqlServerName()))
            : $this->table;

        return $this->connection->table($table)->useWritePdo();
    }

    /**
     * SQL Server's schema grammar names temporary tables "#{prefix}{table}",
     * so queries must use that name instead of the prefixed table name.
     */
    protected function sqlServerName(): string
    {
        return '#'.$this->connection->getTablePrefix().$this->table;
    }

    protected function isSqlServer(): bool
    {
        return $this->connection->getDriverName() === 'sqlsrv';
    }
}
