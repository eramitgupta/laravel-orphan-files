<?php

use Erag\OrphanStorage\Scanner\FileInfo;
use Erag\OrphanStorage\Support\Filters\GracePeriodFilter;
use Illuminate\Support\Facades\Date;

it('keeps files modified after the database was read', function () {
    $this->travelTo('2026-11-15 12:00:00');
    $databaseReadAt = Date::parse('2026-11-15 10:00:00')->getTimestamp();
    $file = new FileInfo('public', 'avatars/new.jpg', 1, $databaseReadAt + 300);

    $reason = (new GracePeriodFilter(60, $databaseReadAt))->reasonToKeep($file);

    expect($reason)->toBe('it was modified after the database was read');
});

it('measures the grace period from when the database was read', function () {
    $this->travelTo('2026-11-15 12:00:00');
    $databaseReadAt = Date::parse('2026-11-15 10:00:00')->getTimestamp();
    $file = new FileInfo('public', 'avatars/recent.jpg', 1, $databaseReadAt - 1800);

    $reason = (new GracePeriodFilter(60, $databaseReadAt))->reasonToKeep($file);

    expect($reason)->toBe('it was modified within the grace period (1 hour)');
});

it('allows cleaning files older than the grace period', function () {
    $this->travelTo('2026-11-15 12:00:00');
    $databaseReadAt = Date::parse('2026-11-15 10:00:00')->getTimestamp();
    $file = new FileInfo('public', 'avatars/old.jpg', 1, $databaseReadAt - 7200);

    expect((new GracePeriodFilter(60, $databaseReadAt))->reasonToKeep($file))->toBeNull();
});
