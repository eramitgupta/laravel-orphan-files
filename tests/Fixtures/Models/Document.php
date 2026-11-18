<?php

namespace Tests\Fixtures\Models;

use Erag\OrphanFiles\Concerns\HasStorageFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasStorageFiles, SoftDeletes;

    public $timestamps = false;

    protected $guarded = [];

    protected array $storageFiles = ['file' => 'path'];

    protected ?string $storageDisk = 'public';
}
