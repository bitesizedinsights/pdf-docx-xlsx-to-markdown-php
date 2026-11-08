<?php

declare(strict_types=1);

use DocumentToMarkdown\Client;
use DocumentToMarkdown\Config;

/**
 * Live test against the real Apify Actor. Costs a few cents.
 *
 * Runs only with APIFY_LIVE_TEST=1 and a token in APIFY_API_TOKEN or APIFY_TOKEN.
 */
const LIVE_SAMPLE_URL = 'https://arxiv.org/pdf/1706.03762';

function liveToken(): ?string
{
    foreach (['APIFY_API_TOKEN', 'APIFY_TOKEN'] as $name) {
        $value = getenv($name);

        if (is_string($value) && $value !== '') {
            return $value;
        }
    }

    return null;
}

function liveEnabled(): bool
{
    return getenv('APIFY_LIVE_TEST') === '1' && liveToken() !== null;
}

/**
 * Fetches the sample file once and keeps it in the system temp directory.
 */
function liveSampleFile(): string
{
    $path = sys_get_temp_dir().'/document-to-markdown-live-'.md5(LIVE_SAMPLE_URL).'.pdf';

    if (! is_file($path) || filesize($path) === 0) {
        $context = stream_context_create([
            'http' => ['timeout' => 60, 'user_agent' => 'document-to-markdown-php-tests'],
        ]);
        $data = file_get_contents(LIVE_SAMPLE_URL, false, $context);

        if ($data === false || $data === '') {
            throw new RuntimeException('Could not fetch the live test sample file');
        }

        file_put_contents($path, $data);
    }

    return $path;
}

test('live: converts a real PDF', function () {
    expect(filesize(liveSampleFile()))->toBeGreaterThan(0);

    $token = liveToken();
    assert($token !== null);

    $client = new Client($token, new Config(apiToken: $token, timeout: 600));

    $result = $client->convert(urls: [LIVE_SAMPLE_URL], chunkSize: 256, maxPagesPerDocument: 2);

    expect($result)->toHaveCount(1);

    $document = $result[0];

    expect($document->isFailed())->toBeFalse($document->error ?? '')
        ->and($document->markdown)->toBeString()->not->toBeEmpty()
        ->and(strtolower((string) $document->markdown))->toContain('attention')
        ->and($document->pagesConverted)->toBeGreaterThan(0)
        ->and($document->pages)->not->toBeEmpty()
        ->and($document->chunks)->not->toBeEmpty();
})->skip(fn () => ! liveEnabled(), 'Set APIFY_LIVE_TEST=1 and APIFY_API_TOKEN (or APIFY_TOKEN) to run live tests');
