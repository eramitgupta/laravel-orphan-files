<?php

use Erag\OrphanFiles\References\Extractors\HtmlExtractor;
use Erag\OrphanFiles\References\Source;

function extractHtml(string $html): array
{
    return (new HtmlExtractor)->extract($html, new Source('posts', 'body', 'html'));
}

it('reads src, href, poster and data-src attributes', function () {
    $html = <<<'HTML'
        <p><img src="/storage/posts/a.jpg"> <a href='https://app.test/storage/files/b.pdf?x=1&amp;y=2'>b</a></p>
        <video poster=posts/poster.png></video><img data-src="posts/lazy.jpg">
        HTML;

    expect(extractHtml($html))->toBe([
        '/storage/posts/a.jpg',
        'https://app.test/storage/files/b.pdf?x=1&y=2',
        'posts/poster.png',
        'posts/lazy.jpg',
    ]);
});

it('reads every candidate of a srcset', function () {
    $html = '<img srcset="posts/a-480.jpg 480w, posts/a-800.jpg 800w,posts/a-1200.jpg 2x">';

    expect(extractHtml($html))->toBe(['posts/a-480.jpg', 'posts/a-800.jpg', 'posts/a-1200.jpg']);
});

it('reads CSS url() values, markdown links and bare URLs', function () {
    $html = '<div style="background: url(\'posts/bg.png\')"></div> ![chart](posts/chart.png) See https://cdn.example.com/posts/c.jpg';

    expect(extractHtml($html))->toBe([
        'posts/bg.png',
        'posts/chart.png',
        'https://cdn.example.com/posts/c.jpg',
    ]);
});

it('ignores fragments and empty attributes', function () {
    expect(extractHtml('<a href="#top">Top</a><img src="">'))->toBe([]);
});

it('reads entity-encoded quotes in inline styles', function () {
    $html = '<div style="background-image: url(&quot;/storage/posts/bg.jpg&quot;);"></div>'
        .'<div style="background: url(&quot;https://app.test/storage/posts/bg2.jpg&quot;)"></div>';

    expect(extractHtml($html))->toBe(['/storage/posts/bg.jpg', 'https://app.test/storage/posts/bg2.jpg']);
});

it('keeps parentheses in file names', function () {
    $html = '<div style="background: url(\'/storage/posts/photo (1).jpg\')"></div> ![chart](/storage/posts/chart(2).png)';

    expect(extractHtml($html))->toBe(['/storage/posts/photo (1).jpg', '/storage/posts/chart(2).png']);
});
