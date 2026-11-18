<?php

use Erag\OrphanFiles\Support\Normalizers\NormalizerPipeline;
use Illuminate\Support\Str;

function interpretations(string $value, string $disk = 'public'): array
{
    return app(NormalizerPipeline::class)->paths($value, $disk);
}

function primaryPath(string $value, string $disk = 'public'): ?string
{
    return app(NormalizerPipeline::class)->primaryPath($value, $disk);
}

it('cleans slashes and dot segments', function (string $value) {
    expect(primaryPath($value))->toBe('avatars/jane.jpg');
})->with([
    'leading slash' => '/avatars/jane.jpg',
    'duplicate slashes' => 'avatars//jane.jpg',
    'backslashes' => 'avatars\\jane.jpg',
    'dot segment' => './avatars/jane.jpg',
    'parent segment' => 'avatars/old/../jane.jpg',
    'surrounding whitespace' => "  avatars/jane.jpg\n",
]);

it('adds the decoded form of URL-encoded paths', function () {
    expect(interpretations('avatars/my%20photo.jpg'))->toBe(['avatars/my%20photo.jpg', 'avatars/my photo.jpg']);
});

it('adds the path without a cache-busting query string', function () {
    expect(interpretations('avatars/jane.jpg?v=3'))->toContain('avatars/jane.jpg');
});

it('maps a Storage::url() back to the disk path', function () {
    expect(primaryPath('https://app.test/storage/avatars/jane.jpg'))->toBe('avatars/jane.jpg');
});

it('maps a root-relative storage URL', function () {
    expect(interpretations('/storage/avatars/jane.jpg'))->toBe(['avatars/jane.jpg', 'storage/avatars/jane.jpg']);
});

it('decodes URL-encoded characters in URLs', function () {
    expect(primaryPath('https://app.test/storage/avatars/my%20photo.jpg'))->toBe('avatars/my photo.jpg');
});

it('maps S3 URLs, removing the root prefix and signed query string', function (string $url) {
    expect(primaryPath($url, 's3'))->toBe('uploads/report.pdf');
})->with([
    'virtual-hosted' => 'https://orphan-bucket.s3.eu-west-1.amazonaws.com/tenant-1/uploads/report.pdf',
    'global endpoint' => 'https://orphan-bucket.s3.amazonaws.com/tenant-1/uploads/report.pdf',
    'path-style' => 'https://s3.eu-west-1.amazonaws.com/orphan-bucket/tenant-1/uploads/report.pdf',
    'presigned' => 'https://orphan-bucket.s3.eu-west-1.amazonaws.com/tenant-1/uploads/report.pdf?X-Amz-Expires=300&X-Amz-Signature=abc123',
    'mixed-case host' => 'https://Orphan-Bucket.S3.eu-west-1.AmazonAWS.com/tenant-1/uploads/report.pdf',
]);

it('maps MinIO path-style endpoint URLs', function () {
    config()->set('filesystems.disks.minio', [
        'driver' => 's3',
        'bucket' => 'media',
        'endpoint' => 'http://minio.local:9000',
        'use_path_style_endpoint' => true,
    ]);

    expect(primaryPath('http://minio.local:9000/media/uploads/report.pdf', 'minio'))->toBe('uploads/report.pdf');
});

it('maps configured CDN prefixes', function () {
    config()->set('orphan-files.url_prefixes.s3', ['https://cdn.example.com']);

    expect(primaryPath('https://cdn.example.com/tenant-1/uploads/report.pdf', 's3'))->toBe('uploads/report.pdf')
        ->and(primaryPath('https://cdn.example.com/uploads/report.pdf', 's3'))->toBe('uploads/report.pdf');
});

it('protects every path suffix of a URL on an unknown host', function () {
    expect(interpretations('https://elsewhere.example/bucket/uploads/report.pdf'))
        ->toBe(['bucket/uploads/report.pdf', 'uploads/report.pdf', 'report.pdf'])
        ->and(primaryPath('https://elsewhere.example/bucket/uploads/report.pdf'))->toBeNull();
});

it('ignores unknown hosts when match_unknown_hosts is disabled', function () {
    config()->set('orphan-files.match_unknown_hosts', false);

    expect(interpretations('https://elsewhere.example/uploads/report.pdf'))->toBe([]);
});

it('maps absolute filesystem paths below a local disk root', function () {
    expect(primaryPath(storage_path('app/public/avatars/jane.jpg')))->toBe('avatars/jane.jpg');
});

it('ignores data, mailto and javascript values', function (string $value) {
    expect(interpretations($value))->toBe([]);
})->with([
    'data URI' => 'data:image/png;base64,iVBORw0KGgo=',
    'mailto' => 'mailto:jane@example.com',
    'javascript' => 'javascript:void(0)',
]);

it('builds case-insensitive keys when configured', function () {
    config()->set('orphan-files.case_sensitive', false);

    expect(app(NormalizerPipeline::class)->key('Avatars/Jane.JPG'))->toBe('avatars/jane.jpg');
});

it('keeps keys case-sensitive by default', function () {
    expect(app(NormalizerPipeline::class)->key('Avatars/Jane.JPG'))->toBe('Avatars/Jane.JPG');
});

it('lets custom normalizers add an interpretation without removing the original', function () {
    config()->set('orphan-files.normalizers', [
        fn (string $path, string $disk): ?string => str_starts_with($path, 'legacy/') ? Str::after($path, 'legacy/') : null,
    ]);

    expect(interpretations('legacy/avatars/jane.jpg'))->toBe(['legacy/avatars/jane.jpg', 'avatars/jane.jpg']);
});

it('maps storage URL paths stored without a leading slash', function () {
    expect(interpretations('storage/avatars/jane.jpg'))->toContain('avatars/jane.jpg');
});

it('treats a doubled leading slash as a path as well as a protocol-relative URL', function () {
    expect(interpretations('//avatars/jane.jpg'))->toContain('avatars/jane.jpg');
});
