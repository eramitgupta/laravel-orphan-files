<?php

namespace Erag\OrphanStorage\References\Extractors;

use Erag\OrphanStorage\Contracts\ReferenceExtractor;
use Erag\OrphanStorage\References\Source;
use Generator;

/**
 * A JSON column. The `key` source option (a dot path such as
 * "images.*.path", or a list of them) selects the values to read. Without
 * a key, every string in the document is collected.
 */
class JsonExtractor implements ReferenceExtractor
{
    public function extract(mixed $value, Source $source): iterable
    {
        $data = $this->decode($value);

        if ($data === null) {
            return;
        }

        if (is_string($data)) {
            yield from $this->strings($data);

            return;
        }

        $keys = array_values(array_filter(
            (array) ($source->options['key'] ?? []),
            fn (mixed $key): bool => is_string($key) && $key !== '',
        ));

        if ($keys === []) {
            yield from $this->strings($data);

            return;
        }

        foreach ($keys as $key) {
            yield from $this->strings(data_get($data, $key));
        }
    }

    protected function decode(mixed $value): mixed
    {
        if (is_array($value) || is_object($value)) {
            return json_decode((string) json_encode($value), true);
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Not JSON at all: treat the raw value as a single path.
            return $value;
        }

        if (is_string($decoded)) {
            $inner = json_decode($decoded, true);

            return json_last_error() === JSON_ERROR_NONE && is_array($inner) ? $inner : $decoded;
        }

        return $decoded;
    }

    /**
     * @return Generator<int, string>
     */
    protected function strings(mixed $data): Generator
    {
        if (is_string($data)) {
            if (trim($data) !== '') {
                yield trim($data);
            }

            return;
        }

        if (is_array($data)) {
            foreach ($data as $item) {
                yield from $this->strings($item);
            }
        }
    }
}
