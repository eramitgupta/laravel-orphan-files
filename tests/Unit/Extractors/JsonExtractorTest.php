<?php

use Erag\OrphanFiles\References\Extractors\JsonExtractor;
use Erag\OrphanFiles\References\Source;

function extractJson(mixed $value, string|array|null $key = null): array
{
    $source = new Source('comments', 'images', 'json', options: $key === null ? [] : ['key' => $key]);

    return iterator_to_array((new JsonExtractor)->extract($value, $source), false);
}

it('reads the values at a wildcard dot key', function () {
    $json = json_encode(['images' => [
        ['path' => 'comments/1.jpg', 'alt' => 'One'],
        ['path' => 'comments/2.jpg', 'alt' => 'Two'],
    ]]);

    expect(extractJson($json, 'images.*.path'))->toBe(['comments/1.jpg', 'comments/2.jpg']);
});

it('collects every string when no key is given', function () {
    $json = json_encode(['cover' => 'covers/a.jpg', 'gallery' => [['src' => 'gallery/b.jpg'], ['src' => 'gallery/c.jpg']], 'count' => 2]);

    expect(extractJson($json))->toBe(['covers/a.jpg', 'gallery/b.jpg', 'gallery/c.jpg']);
});

it('reads several keys', function () {
    $json = json_encode(['cover' => 'covers/a.jpg', 'thumb' => 'thumbs/a.jpg', 'title' => 'Ignored']);

    expect(extractJson($json, ['cover', 'thumb']))->toBe(['covers/a.jpg', 'thumbs/a.jpg']);
});

it('decodes double-encoded JSON', function () {
    $json = json_encode(json_encode([['path' => 'comments/1.jpg']]));

    expect(extractJson($json, '*.path'))->toBe(['comments/1.jpg']);
});

it('treats a value that is not JSON as a single path', function () {
    expect(extractJson('legacy/plain-path.jpg', '*.path'))->toBe(['legacy/plain-path.jpg']);
});

it('returns nothing for null JSON', function () {
    expect(extractJson('null'))->toBe([]);
});
