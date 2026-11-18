<?php

use Erag\OrphanFiles\Diff\TempTableDiffer;
use Erag\OrphanFiles\Exceptions\TemporaryTableUnavailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Str;

uses()->group('database');

afterEach(fn () => Str::createRandomStringsNormally());

it('stores unique hashes and finds the existing ones', function () {
    $differ = new TempTableDiffer(DB::connection());

    $differ->add([referenceHash('a'), referenceHash('b'), referenceHash('b')]);

    expect($differ->count())->toBe(2)
        ->and($differ->existing([referenceHash('a'), referenceHash('c')]))->toBe([referenceHash('a') => true]);
});

it('skips hashes that are already stored', function () {
    $differ = new TempTableDiffer(DB::connection());
    $differ->add([referenceHash('a'), referenceHash('b')]);

    $differ->add([referenceHash('b'), referenceHash('c')]);

    expect($differ->count())->toBe(3)
        ->and(array_keys($differ->existing([referenceHash('a'), referenceHash('b'), referenceHash('c')])))
        ->toEqualCanonicalizing([referenceHash('a'), referenceHash('b'), referenceHash('c')]);
});

it('drops its temporary table on release', function () {
    $table = fixReferenceTableName();
    $differ = new TempTableDiffer(DB::connection());
    $differ->add([referenceHash('a')]);

    $differ->release();

    expect(referenceTableExists($table))->toBeFalse()
        ->and($differ->existing([referenceHash('a')]))->toBe([]);
});

it('throws a clear exception naming the connection when the table cannot be created', function () {
    $differ = new TempTableDiffer(DB::connection(useUnavailableConnection()));

    expect(fn () => $differ->add([referenceHash('a')]))->toThrow(
        TemporaryTableUnavailable::class,
        'Could not create the temporary reference table on database connection [unavailable]',
    );
});

it('reports instead of throwing when the table cannot be dropped', function () {
    Exceptions::fake();
    $differ = new class(DB::connection()) extends TempTableDiffer
    {
        protected function dropTable(): void
        {
            throw new RuntimeException('Connection lost while dropping.');
        }
    };
    $differ->add([referenceHash('a')]);

    $differ->release();

    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'Connection lost while dropping.');
});
