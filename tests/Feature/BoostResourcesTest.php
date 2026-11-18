<?php

use Illuminate\Support\Facades\Blade;

/**
 * Boost renders package guidelines with Blade and silently drops a guideline that fails to render.
 */
it('renders the Boost guideline to Markdown with its code snippets intact', function () {
    $guideline = (string) file_get_contents(dirname(__DIR__, 2).'/resources/boost/guidelines/core.blade.php');

    $rendered = Blade::render($guideline);

    expect($rendered)
        ->toContain('php artisan orphan:clean')
        ->toContain("['model' => App\\Models\\Comment::class, 'column' => 'images', 'type' => 'json', 'key' => '*.path']")
        ->not->toContain('@verbatim');
});

it('gives the setup skill the frontmatter Boost requires', function () {
    $path = dirname(__DIR__, 2).'/resources/boost/skills/orphan-files-setup/SKILL.md';
    preg_match('/\A---\R(.*?)\R---\R/s', (string) file_get_contents($path), $frontmatter);
    preg_match_all('/^(name|description):\s*"?(.*?)"?\s*$/m', $frontmatter[1] ?? '', $fields);

    $values = array_combine($fields[1], $fields[2]);

    expect($values['name'] ?? null)->toBe('orphan-files-setup')
        ->and($values['description'] ?? '')->toContain('erag/laravel-orphan-files');
});
