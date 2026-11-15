<?php

namespace Erag\OrphanStorage\Contracts;

use Erag\OrphanStorage\Scanner\FileInfo;

interface FileFilter
{
    /**
     * Explain why an unreferenced file must be kept, or return null when
     * the file may be cleaned.
     */
    public function reasonToKeep(FileInfo $file): ?string;
}
