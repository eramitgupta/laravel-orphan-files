<?php

namespace Tests\Fixtures\Models;

use Erag\OrphanFiles\Concerns\HasStorageFiles;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasStorageFiles;

    public $timestamps = false;

    protected $guarded = [];

    protected array $storageFiles = ['avatar' => 'path'];

    protected ?string $storageDisk = 'public';
}
