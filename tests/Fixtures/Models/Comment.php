<?php

namespace Tests\Fixtures\Models;

use Erag\OrphanFiles\Concerns\HasStorageFiles;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasStorageFiles;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['images' => 'array'];

    protected array $storageFiles = [
        'images' => ['type' => 'json', 'key' => '*.path'],
    ];
}
