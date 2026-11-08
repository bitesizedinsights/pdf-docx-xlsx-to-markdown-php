<?php

declare(strict_types=1);

use DocumentToMarkdown\Client;
use DocumentToMarkdown\Config;
use DocumentToMarkdown\Exception\ApiException;
use DocumentToMarkdown\Exception\RateLimitException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/** @return array<string, mixed> */
function sentBody(RequestInterface $request): array
{
    /** @var array<string, mixed> */
    return json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
}

test('convert runs the actor and maps the dataset', function () {
    $history = [];
    $client = makeClient([
        runResponse(),
        jsonResponse(200, [loadFixture('document_full'), loadFixture('document_failed')]),
    ], $history);

    $result = $client->convert(['https://example.com/report.pdf', 'https://example.com/notes.txt']);

    expect($result)->toHaveCount(2)
        ->and($result[0]->isSuccessful())->toBeTrue()
        ->and($result[1]->isFailed())->toBeTrue()
        ->and($history)->toHaveCount(2);

    $post = $history[0]['request'];
    expect($post->getMethod())->toBe('POST')
        ->and($post->getUri()->getPath())->toBe('/v2/acts/bite-sized-insights~document-to-markdown/runs')
        ->and($post->getHeaderLine('Authorization'))->toBe('Bearer test-token')
        ->and(sentBody($post))->toBe([
            'urls' => ['https://example.com/report.pdf', 'https://example.com/notes.txt'],
            'ocr' => true,
            'extractTables' => true,
            'fastMode' => true,
            'chunkSize' => 0,
            'maxPagesPerDocument' => 100,
        ]);

    $get = $history[1]['request'];
    expect($get->getMethod())->toBe('GET')
        ->and($get->getUri()->getPath())->toBe('/v2/datasets/ds1/items')
        ->and($get->getHeaderLine('Authorization'))->toBe('Bearer test-token');
});

test('convert sends all options', function () {
    $history = [];
    $client = makeClient([runResponse(), jsonResponse(200, [])], $history);

    $client->convert(
        urls: ['https://example.com/a.pdf'],
        ocr: false,
        extractTables: false,
        fastMode: false,
        chunkSize: 512,
        maxPagesPerDocument: 15,
    );

    expect(sentBody($history[0]['request']))->toBe([
        'urls' => ['https://example.com/a.pdf'],
        'ocr' => false,
        'extractTables' => false,
        'fastMode' => false,
        'chunkSize' => 512,
        'maxPagesPerDocument' => 15,
    ]);
});

test('empty dataset gives an empty list', function () {
    $client = makeClient([runResponse(), jsonResponse(200, [])]);

    expect($client->convert(['https://example.com/a.pdf']))->toBe([]);
});

test('waits for a running run until it succeeds', function () {
    $history = [];
    $client = makeClient([
        runResponse('RUNNING', 'run42'),
        runResponse('RUNNING', 'run42'),
        runResponse('SUCCEEDED', 'run42'),
        jsonResponse(200, [loadFixture('document_full')]),
    ], $history);

    $result = $client->convert(['https://example.com/report.pdf']);

    expect($result)->toHaveCount(1)
        ->and($history)->toHaveCount(4)
        ->and($history[1]['request']->getUri()->getPath())->toBe('/v2/actor-runs/run42')
        ->and($history[1]['request']->getUri()->getQuery())->toStartWith('waitForFinish=');
});

test('throws when the run does not finish in time', function () {
    $client = makeClient([runResponse('RUNNING')], config: new Config('t', timeout: 0));

    expect(fn () => $client->convert(['https://example.com/a.pdf']))
        ->toThrow(ApiException::class, 'did not finish within 0 seconds');
});

test('throws on failed run status', function (string $status) {
    $client = makeClient([runResponse($status)]);

    expect(fn () => $client->convert(['https://example.com/a.pdf']))
        ->toThrow(ApiException::class, "status {$status}");
})->with(['FAILED', 'ABORTED', 'TIMED-OUT']);

test('throws on unknown run status', function () {
    $client = makeClient([runResponse('WEIRD')]);

    expect(fn () => $client->convert(['https://example.com/a.pdf']))
        ->toThrow(ApiException::class, 'unknown run status');
});

test('throws when a running run has no id', function () {
    $client = makeClient([jsonResponse(201, ['data' => ['status' => 'RUNNING']])]);

    expect(fn () => $client->convert(['https://example.com/a.pdf']))
        ->toThrow(ApiException::class, 'missing run ID');
});

test('throws when the run data is missing', function () {
    $client = makeClient([jsonResponse(201, ['foo' => 'bar'])]);

    expect(fn () => $client->convert(['https://example.com/a.pdf']))
        ->toThrow(ApiException::class, 'missing run data');
});

test('throws when the dataset id is missing', function () {
    $client = makeClient([runResponse(datasetId: null)]);

    expect(fn () => $client->convert(['https://example.com/a.pdf']))
        ->toThrow(ApiException::class, 'missing dataset ID');
});

test('429 maps to RateLimitException with Retry-After', function () {
    $client = makeClient([jsonResponse(429, ['error' => 'slow down'], ['Retry-After' => '12'])]);

    try {
        $client->convert(['https://example.com/a.pdf']);
        $this->fail('Expected RateLimitException');
    } catch (RateLimitException $e) {
        expect($e->retryAfter)->toBe(12)
            ->and($e)->toBeInstanceOf(ApiException::class);
    }
});

test('429 without Retry-After defaults to 60 seconds', function () {
    $client = makeClient([jsonResponse(429, ['error' => 'slow down'])]);

    try {
        $client->convert(['https://example.com/a.pdf']);
        $this->fail('Expected RateLimitException');
    } catch (RateLimitException $e) {
        expect($e->retryAfter)->toBe(60);
    }
});

test('500 maps to ApiException with the status code', function () {
    $client = makeClient([new Response(500, [], 'boom')]);

    try {
        $client->convert(['https://example.com/a.pdf']);
        $this->fail('Expected ApiException');
    } catch (ApiException $e) {
        expect($e)->not->toBeInstanceOf(RateLimitException::class)
            ->and($e->getCode())->toBe(500)
            ->and($e->getMessage())->toContain('status 500');
    }
});

test('connection errors map to ApiException', function () {
    $client = makeClient([new ConnectException('no route', new Request('POST', 'https://api.apify.com'))]);

    expect(fn () => $client->convert(['https://example.com/a.pdf']))
        ->toThrow(ApiException::class, 'API request failed: no route');
});

test('malformed JSON maps to ApiException', function () {
    $client = makeClient([new Response(200, [], '{not json')]);

    expect(fn () => $client->convert(['https://example.com/a.pdf']))
        ->toThrow(ApiException::class, 'malformed JSON');
});

test('non-object JSON maps to ApiException', function () {
    $client = makeClient([new Response(200, [], '"text"')]);

    expect(fn () => $client->convert(['https://example.com/a.pdf']))
        ->toThrow(ApiException::class, 'unexpected JSON');
});

test('invalid dataset item names its index', function () {
    $broken = [...loadFixture('document_full'), 'status' => 'weird'];
    $client = makeClient([runResponse(), jsonResponse(200, [loadFixture('document_full'), $broken])]);

    expect(fn () => $client->convert(['https://example.com/a.pdf']))
        ->toThrow(ApiException::class, 'Invalid dataset item at index 1');
});

test('non-object dataset item names its index', function () {
    $client = makeClient([runResponse(), jsonResponse(200, ['text'])]);

    expect(fn () => $client->convert(['https://example.com/a.pdf']))
        ->toThrow(ApiException::class, 'Invalid dataset item at index 0');
});

test('custom config changes actor, base url and request timeout', function () {
    $history = [];
    $config = new Config('other-token', 'acme/custom', 'https://proxy.example.com/v2/', requestTimeout: 5);
    $client = makeClient([runResponse(), jsonResponse(200, [])], $history, $config);

    $client->convert(['https://example.com/a.pdf']);

    $request = $history[0]['request'];
    expect($request->getUri()->getHost())->toBe('proxy.example.com')
        ->and($request->getUri()->getPath())->toBe('/v2/acts/acme~custom/runs')
        ->and($request->getHeaderLine('Authorization'))->toBe('Bearer other-token');
});

test('client works with its default HTTP client', function () {
    expect(new Client('token'))->toBeInstanceOf(Client::class);
});
