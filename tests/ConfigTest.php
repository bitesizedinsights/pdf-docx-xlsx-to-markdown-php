<?php

declare(strict_types=1);

use DocumentToMarkdown\Config;

test('config has defaults', function () {
    $config = new Config('token');

    expect($config->apiToken)->toBe('token')
        ->and($config->actorId)->toBe('bite-sized-insights/document-to-markdown')
        ->and($config->baseUrl)->toBe('https://api.apify.com/v2')
        ->and($config->timeout)->toBe(600)
        ->and($config->requestTimeout)->toBe(90);
});

test('config accepts overrides', function () {
    $config = new Config('t', 'me/other', 'https://example.com/v2', 30, 10);

    expect($config->actorId)->toBe('me/other')
        ->and($config->baseUrl)->toBe('https://example.com/v2')
        ->and($config->timeout)->toBe(30)
        ->and($config->requestTimeout)->toBe(10);
});
