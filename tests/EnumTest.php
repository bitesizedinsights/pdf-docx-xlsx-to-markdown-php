<?php

declare(strict_types=1);

use DocumentToMarkdown\DocumentFormat;
use DocumentToMarkdown\DocumentStatus;
use DocumentToMarkdown\ErrorCode;
use DocumentToMarkdown\PageMethod;

test('enum values', function (string $enum, array $values) {
    expect(array_map(static fn ($case) => $case->value, $enum::cases()))->toBe($values);
})->with([
    'status' => [DocumentStatus::class, ['success', 'partial', 'failed']],
    'format' => [DocumentFormat::class, ['pdf', 'docx', 'pptx', 'xlsx', 'html', 'image']],
    'page method' => [PageMethod::class, ['fast', 'layout', 'ocr', 'native']],
    'error code' => [ErrorCode::class, [
        'blocked_url', 'download_failed', 'too_large', 'unsupported_format', 'invalid_document',
        'too_many_pages', 'conversion_failed', 'service_unavailable',
    ]],
]);

test('unknown enum values give null', function (string $enum) {
    expect($enum::tryFrom('nope'))->toBeNull();
})->with([DocumentStatus::class, DocumentFormat::class, PageMethod::class, ErrorCode::class]);
