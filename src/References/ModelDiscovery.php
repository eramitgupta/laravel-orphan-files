<?php

namespace Erag\OrphanFiles\References;

use Erag\OrphanFiles\Concerns\HasStorageFiles;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use ReflectionClass;
use Throwable;

/**
 * Finds Eloquent models that use the HasStorageFiles trait.
 */
class ModelDiscovery
{
    /**
     * Discovered models per discovery configuration, so repeated lookups do not re-read the model files.
     *
     * @var array<string, list<class-string<Model>>>
     */
    protected array $discovered = [];

    public function __construct(
        protected Repository $config,
        protected Filesystem $files,
    ) {}

    /**
     * @return list<class-string<Model>>
     */
    public function models(): array
    {
        $settings = (array) $this->config->get('orphan-files.discovery', []);
        $cacheKey = md5((string) json_encode($settings));

        return $this->discovered[$cacheKey] ??= $this->discover();
    }

    /**
     * @return list<class-string<Model>>
     */
    protected function discover(): array
    {
        $candidates = (array) $this->config->get('orphan-files.discovery.models', []);

        if ($this->config->get('orphan-files.discovery.enabled', true)) {
            foreach ((array) $this->config->get('orphan-files.discovery.paths', []) as $path) {
                if (! is_string($path) || ! $this->files->isDirectory($path)) {
                    continue;
                }

                foreach ($this->files->allFiles($path) as $file) {
                    if ($file->getExtension() === 'php' && ($class = $this->classFromFile($file->getPathname())) !== null) {
                        $candidates[] = $class;
                    }
                }
            }
        }

        $models = [];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $this->usesTrait($candidate)) {
                $models[$candidate] = $candidate;
            }
        }

        return array_values($models);
    }

    /**
     * The sources declared by every discovered model.
     *
     * @return list<Source>
     */
    public function sources(): array
    {
        $sources = [];

        foreach ($this->models() as $model) {
            array_push($sources, ...(new $model)->storageFileSources());
        }

        return $sources;
    }

    public function usesTrait(string $class): bool
    {
        try {
            if (! class_exists($class)) {
                return false;
            }

            $reflection = new ReflectionClass($class);

            return ! $reflection->isAbstract()
                && $reflection->isSubclassOf(Model::class)
                && in_array(HasStorageFiles::class, class_uses_recursive($class), true);
        } catch (Throwable) {
            return false;
        }
    }

    protected function classFromFile(string $path): ?string
    {
        $contents = (string) file_get_contents($path);

        if (preg_match('/^\s*(?:(?:abstract|final|readonly)\s+)*class\s+(\w+)/m', $contents, $class) !== 1) {
            return null;
        }

        $namespace = preg_match('/^\s*namespace\s+([^;{\s]+)/m', $contents, $matches) === 1 ? $matches[1].'\\' : '';

        return $namespace.$class[1];
    }
}
