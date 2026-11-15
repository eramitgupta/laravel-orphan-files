<?php

use Erag\OrphanStorage\References\Extractors\UrlExtractor;
use Erag\OrphanStorage\References\Source;

it('returns the trimmed URL for normalisation', function () {
    $urls = (new UrlExtractor)->extract(' https://cdn.example.com/a.jpg ', new Source('products', 'image_url', 'url'));

    expect($urls)->toBe(['https://cdn.example.com/a.jpg']);
});

it('returns nothing for empty values', function () {
    expect((new UrlExtractor)->extract('', new Source('products', 'image_url', 'url')))->toBe([]);
});
