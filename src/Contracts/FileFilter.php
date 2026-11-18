<?php

namespace Erag\OrphanFiles\Contracts;

use Erag\OrphanFiles\Scanner\FileInfo;

interface FileFilter
{
    /**
     * Explain why an unreferenced file must be kept, or return null when
     * the file may be cleaned.
     */
    public function reasonToKeep(FileInfo $file): ?string;
}
