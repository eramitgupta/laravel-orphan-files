<?php

use Illuminate\Database\Query\Expression;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

/**
 * Store a file and set its modification time, so grace periods are deterministic.
 */
function storeFile(string $disk, string $path, string $contents = 'file', int $hoursOld = 48): void
{
    Storage::disk($disk)->put($path, $contents);

    touch(Storage::disk($disk)->path($path), Date::now()->getTimestamp() - $hoursOld * 3600);
    clearstatcache();
}

/**
 * Put a file straight into the trash layout: "{trash}/{disk}/{date}/{path}".
 */
function storeTrashedFile(string $disk, string $date, string $path, string $contents = 'trashed'): void
{
    Storage::disk($disk)->put(".orphan-trash/{$disk}/{$date}/{$path}", $contents);
}

/**
 * Run the scan command and decode its JSON report.
 *
 * @param  array<string, mixed>  $options
 * @return array<string, mixed>
 */
function scanReport(array $options = []): array
{
    Artisan::call('orphan:scan', ['--json' => true, ...$options], $output = new BufferedOutput);

    return json_decode($output->fetch(), true);
}

/**
 * A 32 character hash, the length the temporary reference table stores.
 */
function referenceHash(string $value): string
{
    return md5($value);
}

/**
 * Make temporary reference tables use a known name.
 */
function fixReferenceTableName(): string
{
    Str::createRandomStringsUsing(fn (): string => 'fixedtablename16');

    return 'orphan_storage_refs_fixedtablename16';
}

/**
 * Whether a temporary reference table still exists on the default connection.
 */
function referenceTableExists(string $table): bool
{
    $connection = DB::connection();
    $name = $connection->getDriverName() === 'sqlsrv'
        ? new Expression($connection->getQueryGrammar()->wrap('#'.$connection->getTablePrefix().$table))
        : $table;

    try {
        $connection->table($name)->useWritePdo()->exists();

        return true;
    } catch (QueryException) {
        return false;
    }
}

/**
 * Register a database connection that cannot be opened.
 */
function useUnavailableConnection(): string
{
    config()->set('database.connections.unavailable', [
        'driver' => 'sqlite',
        'database' => '/nonexistent/orphan-storage/database.sqlite',
        'prefix' => '',
    ]);

    return 'unavailable';
}
