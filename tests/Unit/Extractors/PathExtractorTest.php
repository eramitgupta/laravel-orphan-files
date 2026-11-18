<?php

use Erag\OrphanFiles\References\Extractors\PathExtractor;
use Erag\OrphanFiles\References\Source;

it('returns the trimmed path', function () {
    $paths = (new PathExtractor)->extract('  avatars/jane.jpg ', new Source('users', 'avatar'));

    expect($paths)->toBe(['avatars/jane.jpg']);
});

it('splits values on the configured separator', function () {
    $source = new Source('users', 'gallery', options: ['separator' => ',']);

    $paths = (new PathExtractor)->extract('a.jpg, b.jpg,,c.jpg', $source);

    expect($paths)->toBe(['a.jpg', 'b.jpg', 'c.jpg']);
});

it('returns nothing for empty or non-string values', function (mixed $value) {
    expect((new PathExtractor)->extract($value, new Source('users', 'avatar')))->toBe([]);
})->with([
    'empty string' => '',
    'whitespace' => '   ',
    'integer' => 42,
    'array' => [['a.jpg']],
]);
