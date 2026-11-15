<?php

use Erag\OrphanStorage\References\Source;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\Models\Comment;
use Tests\Fixtures\Models\Document;
use Tests\Fixtures\Models\User;

it('declares its storage columns as sources', function () {
    $sources = (new Comment)->storageFileSources();

    expect($sources)->toHaveCount(1)
        ->and($sources[0])->toBeInstanceOf(Source::class)
        ->and([$sources[0]->table, $sources[0]->column, $sources[0]->type, $sources[0]->options])
        ->toBe(['comments', 'images', 'json', ['key' => '*.path']]);
});

it('never touches storage when a model is updated or deleted', function () {
    Storage::fake('public');
    storeFile('public', 'avatars/old.jpg');
    storeFile('public', 'documents/contract.pdf');
    $user = User::create(['avatar' => 'avatars/old.jpg']);
    $document = Document::create(['file' => 'documents/contract.pdf']);

    $user->update(['avatar' => 'avatars/new.jpg']);
    $user->delete();
    $document->forceDelete();

    Storage::disk('public')->assertExists(['avatars/old.jpg', 'documents/contract.pdf']);
    Storage::disk('public')->assertMissing('.orphan-trash');
});
