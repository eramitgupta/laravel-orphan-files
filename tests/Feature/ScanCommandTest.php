<?php

use Erag\OrphanStorage\Events\OrphanScanCompleted;
use Erag\OrphanStorage\Jobs\RunScanJob;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\Models\User;

it('summarises files, referenced files, orphans and reclaimable size', function () {
    Storage::fake('public');
    storeFile('public', 'avatars/kept.jpg', 'kept');
    storeFile('public', 'avatars/orphan.jpg', 'orphan!');
    storeFile('public', 'avatars/new.jpg', 'new', hoursOld: 1);
    config()->set('orphan-storage.sources', [['table' => 'users', 'column' => 'avatar', 'type' => 'path']]);
    DB::table('users')->insert(['avatar' => 'avatars/kept.jpg']);

    $report = scanReport();

    expect($report['totals'])->toBe([
        'files' => 3,
        'referenced' => 1,
        'orphans' => 2,
        'protected' => 1,
        'cleanable' => 1,
        'total_bytes' => 14,
        'orphan_bytes' => 10,
        'reclaimable_bytes' => 7,
    ])->and($report['disks']['public']['protected_by'])->toBe(['grace_period' => 1]);
});

it('renders a summary table with a hint to preview the clean', function () {
    Storage::fake('public');
    storeFile('public', 'avatars/orphan.jpg');

    $this->artisan('orphan:scan')
        ->expectsOutputToContain('Reclaimable')
        ->expectsOutputToContain('php artisan orphan:clean')
        ->assertSuccessful();
});

it('exports orphans to a CSV file', function () {
    Storage::fake('public');
    storeFile('public', 'avatars/orphan.jpg', 'orphan!');
    $path = sys_get_temp_dir().'/orphan-storage-'.uniqid().'.csv';

    $this->artisan('orphan:scan', ['--export' => 'csv', '--output' => $path])->assertSuccessful();

    $rows = array_map(fn (string $line): array => str_getcsv($line, escape: '\\'), file($path, FILE_IGNORE_NEW_LINES));
    unlink($path);

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toBe(['disk', 'path', 'size', 'last_modified', 'status', 'reason'])
        ->and([$rows[1][0], $rows[1][1], $rows[1][2], $rows[1][4]])->toBe(['public', 'avatars/orphan.jpg', '7', 'cleanable']);
});

it('rejects unsupported export formats', function () {
    $this->artisan('orphan:scan', ['--export' => 'xml'])
        ->expectsOutputToContain('Unsupported export format [xml]')
        ->assertFailed();
});

it('limits the scan to the given disk and directories', function () {
    Storage::fake('public');
    Storage::fake('s3');
    config()->set('orphan-storage.disks', ['public' => [''], 's3' => ['uploads/']]);
    storeFile('public', 'avatars/a.jpg');
    storeFile('public', 'posts/b.jpg');
    storeFile('s3', 'uploads/c.jpg');

    $report = scanReport(['--disk' => ['public'], '--dir' => ['avatars']]);

    expect(array_keys($report['disks']))->toBe(['public'])
        ->and($report['disks']['public']['directories'])->toBe(['avatars/'])
        ->and($report['totals']['files'])->toBe(1);
});

it('never counts files in the trash folder', function () {
    Storage::fake('public');
    storeFile('public', 'avatars/orphan.jpg');
    storeTrashedFile('public', '2026-01-01', 'avatars/old.jpg');

    expect(scanReport()['totals']['files'])->toBe(1);
});

it('fails with a clear error when a source table is missing', function () {
    Storage::fake('public');
    config()->set('orphan-storage.sources', [['table' => 'missing_table', 'column' => 'path']]);

    $this->artisan('orphan:scan')
        ->expectsOutputToContain("table [missing_table] doesn't exist")
        ->assertFailed();
});

it('fails when a disk is not listed in the config', function () {
    $this->artisan('orphan:scan', ['--disk' => ['s3']])
        ->expectsOutputToContain("Disk [s3] isn't listed under \"disks\"")
        ->assertFailed();
});

it('queues the scan', function () {
    Queue::fake([RunScanJob::class]);

    $this->artisan('orphan:scan', ['--queue' => true, '--disk' => ['public']])->assertSuccessful();

    Queue::assertPushed(RunScanJob::class, fn (RunScanJob $job): bool => $job->options->disks === ['public']);
});

it('dispatches OrphanScanCompleted with the report', function () {
    Storage::fake('public');
    storeFile('public', 'avatars/orphan.jpg');
    Event::fake([OrphanScanCompleted::class]);

    $this->artisan('orphan:scan')->assertSuccessful();

    Event::assertDispatched(OrphanScanCompleted::class, fn (OrphanScanCompleted $event): bool => $event->report->totals()['orphans'] === 1);
});

it('discovers models that use HasStorageFiles', function () {
    config()->set('orphan-storage.discovery.paths', [__DIR__.'/../Fixtures/Models']);
    Storage::fake('public');
    storeFile('public', 'avatars/jane.jpg');
    storeFile('public', 'avatars/orphan.jpg');
    User::create(['avatar' => 'avatars/jane.jpg']);

    $totals = scanReport()['totals'];

    expect([$totals['referenced'], $totals['cleanable']])->toBe([1, 1]);
});

it('fails when a source has no primary key to read it in chunks', function () {
    config()->set('orphan-storage.sources', [['table' => 'users', 'column' => 'avatar', 'primary_key' => null]]);

    $this->artisan('orphan:scan')
        ->expectsOutputToContain('a unique "primary_key" is required')
        ->assertFailed();
});

it('reads source tables from the primary even when a replica lags behind', function () {
    Storage::fake('public');
    $primary = tempnam(sys_get_temp_dir(), 'orphan-primary');
    $replica = tempnam(sys_get_temp_dir(), 'orphan-replica');
    config()->set('database.connections.replica_only', ['driver' => 'sqlite', 'database' => $replica, 'prefix' => '']);
    config()->set('database.connections.split', [
        'driver' => 'sqlite',
        'prefix' => '',
        'read' => ['database' => $replica],
        'write' => ['database' => $primary],
    ]);
    foreach (['split', 'replica_only'] as $connection) {
        Schema::connection($connection)->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('avatar')->nullable();
        });
    }
    DB::connection('split')->table('users')->insert(['avatar' => 'avatars/new-upload.jpg']);
    config()->set('orphan-storage.sources', [['connection' => 'split', 'table' => 'users', 'column' => 'avatar', 'type' => 'path']]);
    storeFile('public', 'avatars/new-upload.jpg');

    $totals = scanReport()['totals'];
    DB::purge('split');
    DB::purge('replica_only');
    unlink($primary);
    unlink($replica);

    expect([$totals['referenced'], $totals['cleanable']])->toBe([1, 0]);
});
