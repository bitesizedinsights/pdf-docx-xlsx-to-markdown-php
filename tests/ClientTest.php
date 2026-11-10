<?php

declare(strict_types=1);

use DocumentToMarkdown\Client;
use DocumentToMarkdown\Config;
use DocumentToMarkdown\Exception\ApiException;
use DocumentToMarkdown\Exception\MissingApiKeyException;
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
    $client = makeClient([runResponse('RUNNING')], config: new Config(timeout: 0));

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
    $config = new Config('acme/custom', 'https://proxy.example.com/v2/', requestTimeout: 5);
    $client = makeClient([runResponse(), jsonResponse(200, [])], $history, $config);

    $client->convert(['https://example.com/a.pdf']);

    $request = $history[0]['request'];
    expect($request->getUri()->getHost())->toBe('proxy.example.com')
        ->and($request->getUri()->getPath())->toBe('/v2/acts/acme~custom/runs')
        ->and($request->getHeaderLine('Authorization'))->toBe('Bearer test-token');
});

test('client works with its default HTTP client', function () {
    expect(new Client)->toBeInstanceOf(Client::class);
});

test('setApifyKey is fluent and sets the bearer token', function () {
    $history = [];
    $client = makeClient([runResponse(), jsonResponse(200, [])], $history, apiKey: null);

    expect($client->setApifyKey('my-key'))->toBe($client);

    $client->convert(['https://example.com/a.pdf']);

    expect($history[0]['request']->getHeaderLine('Authorization'))->toBe('Bearer my-key');
});

test('falls back to the APIFY_API_TOKEN and APIFY_TOKEN environment variables', function () {
    $previous = [getenv('APIFY_API_TOKEN'), getenv('APIFY_TOKEN')];

    try {
        putenv('APIFY_API_TOKEN');
        putenv('APIFY_TOKEN=env-token-2');
        $history = [];
        makeClient([runResponse(), jsonResponse(200, [])], $history, apiKey: null)->convert(['https://example.com/a.pdf']);
        expect($history[0]['request']->getHeaderLine('Authorization'))->toBe('Bearer env-token-2');

        putenv('APIFY_API_TOKEN=env-token-1');
        $history = [];
        makeClient([runResponse(), jsonResponse(200, [])], $history, apiKey: null)->convert(['https://example.com/a.pdf']);
        expect($history[0]['request']->getHeaderLine('Authorization'))->toBe('Bearer env-token-1');
    } finally {
        putenv($previous[0] === false ? 'APIFY_API_TOKEN' : 'APIFY_API_TOKEN='.$previous[0]);
        putenv($previous[1] === false ? 'APIFY_TOKEN' : 'APIFY_TOKEN='.$previous[1]);
    }
});

test('throws MissingApiKeyException when no key is set anywhere', function () {
    $previous = [getenv('APIFY_API_TOKEN'), getenv('APIFY_TOKEN')];

    try {
        putenv('APIFY_API_TOKEN');
        putenv('APIFY_TOKEN');
        $history = [];
        $client = makeClient([], $history, apiKey: null);

        expect(fn () => $client->convert(['https://example.com/a.pdf']))->toThrow(MissingApiKeyException::class)
            ->and($history)->toBeEmpty();
    } finally {
        putenv($previous[0] === false ? 'APIFY_API_TOKEN' : 'APIFY_API_TOKEN='.$previous[0]);
        putenv($previous[1] === false ? 'APIFY_TOKEN' : 'APIFY_TOKEN='.$previous[1]);
    }
});

test('429 is retried after the Retry-After wait and then succeeds', function () {
    $history = [];
    $sleeps = [];
    $client = makeClient([
        jsonResponse(429, ['error' => 'slow'], ['Retry-After' => '5']),
        runResponse(),
        jsonResponse(200, []),
    ], $history, new Config, sleeps: $sleeps);

    expect($client->convert(['https://example.com/a.pdf']))->toBe([])
        ->and($sleeps)->toHaveCount(1)
        ->and($sleeps[0])->toBeGreaterThanOrEqual(5.0)
        ->and($history)->toHaveCount(3);
});

test('Retry-After in HTTP-date form is honoured', function () {
    $sleeps = [];
    $date = gmdate('D, d M Y H:i:s', time() + 10).' GMT';
    $client = makeClient([
        jsonResponse(429, [], ['Retry-After' => $date]),
        runResponse(),
        jsonResponse(200, []),
    ], config: new Config, sleeps: $sleeps);

    $client->convert(['https://example.com/a.pdf']);

    expect($sleeps)->toHaveCount(1)
        ->and($sleeps[0])->toBeGreaterThanOrEqual(8.0)
        ->and($sleeps[0])->toBeLessThanOrEqual(10.0);
});

test('429 without Retry-After falls back to backoff instead of 60 seconds', function () {
    $sleeps = [];
    $client = makeClient([
        jsonResponse(429, []),
        runResponse(),
        jsonResponse(200, []),
    ], config: new Config, sleeps: $sleeps);

    $client->convert(['https://example.com/a.pdf']);

    expect($sleeps)->toHaveCount(1)
        ->and($sleeps[0])->toBeBetween(0.5, 1.0);
});

test('429 with a Retry-After above the maximum delay is not waited out', function () {
    $sleeps = [];
    $client = makeClient([jsonResponse(429, [], ['Retry-After' => '120'])], config: new Config, sleeps: $sleeps);

    expect(fn () => $client->convert(['https://example.com/a.pdf']))->toThrow(RateLimitException::class)
        ->and($sleeps)->toBe([]);
});

test('GET requests retry on 5xx with growing backoff, then give up', function () {
    $sleeps = [];
    $client = makeClient([
        runResponse('RUNNING'),
        new Response(503, [], 'a'),
        new Response(502, [], 'b'),
        new Response(500, [], 'c'),
        new Response(500, [], 'd'),
    ], config: new Config(maxRetries: 3), sleeps: $sleeps);

    expect(fn () => $client->convert(['https://example.com/a.pdf']))
        ->toThrow(ApiException::class, 'status 500')
        ->and($sleeps)->toHaveCount(3)
        ->and($sleeps[0])->toBeBetween(0.5, 1.0)
        ->and($sleeps[1])->toBeBetween(1.0, 2.0)
        ->and($sleeps[2])->toBeBetween(2.0, 4.0);
});

test('starting a run is not repeated after a 5xx', function () {
    $history = [];
    $sleeps = [];
    $client = makeClient([new Response(500, [], 'boom'), runResponse()], $history, new Config, sleeps: $sleeps);

    expect(fn () => $client->convert(['https://example.com/a.pdf']))->toThrow(ApiException::class, 'status 500')
        ->and($history)->toHaveCount(1)
        ->and($sleeps)->toBe([]);
});

test('a failed connection is retried, also when starting a run', function () {
    $sleeps = [];
    $client = makeClient([
        new ConnectException('no route', new Request('POST', 'https://api.apify.com')),
        runResponse(),
        jsonResponse(200, []),
    ], config: new Config, sleeps: $sleeps);

    expect($client->convert(['https://example.com/a.pdf']))->toBe([])
        ->and($sleeps)->toHaveCount(1);
});

test('maxRetries 0 turns retrying off', function () {
    $history = [];
    $client = makeClient([jsonResponse(429, [], ['Retry-After' => '1']), runResponse()], $history);

    expect(fn () => $client->convert(['https://example.com/a.pdf']))->toThrow(RateLimitException::class)
        ->and($history)->toHaveCount(1);
});

test('4xx other than 429 is never retried', function () {
    $history = [];
    $client = makeClient([new Response(401, [], 'no'), runResponse()], $history, new Config);

    expect(fn () => $client->convert(['https://example.com/a.pdf']))->toThrow(ApiException::class, 'status 401')
        ->and($history)->toHaveCount(1);
});

it('rejects input outside the Actor schema limits before any request', function (array $args, string $message): void {
    $history = [];
    $client = makeClient([], $history);

    expect(fn () => $client->convert(...$args))->toThrow(InvalidArgumentException::class, $message);
    expect($history)->toBeEmpty();
})->with([
    'no urls' => [[[]], 'urls must contain 1 to 100 links, got 0'],
    'too many urls' => [[array_fill(0, 101, 'https://a.test/x.pdf')], 'got 101'],
    'non-string url' => [[['https://a.test/x.pdf', 5]], 'urls[1] must be a string'],
    'url too long' => [[['https://a.test/'.str_repeat('a', 2048)]], 'urls[0] must be at most 2048'],
    'chunk too small' => [[['https://a.test/x.pdf'], 'chunkSize' => 50], 'chunkSize must be 0'],
    'chunk too large' => [[['https://a.test/x.pdf'], 'chunkSize' => 4001], 'chunkSize must be 0'],
    'pages zero' => [[['https://a.test/x.pdf'], 'maxPagesPerDocument' => 0], 'maxPagesPerDocument must be 1 to 200'],
    'pages too large' => [
        [['https://a.test/x.pdf'], 'maxPagesPerDocument' => 201],
        'maxPagesPerDocument must be 1 to 200',
    ],
]);

it('accepts the boundary values of the Actor schema', function (): void {
    $history = [];
    $client = makeClient([runResponse(), jsonResponse(200, [])], $history);

    $client->convert(array_fill(0, 100, 'https://a.test/x.pdf'), chunkSize: 4000, maxPagesPerDocument: 200);

    expect($history)->toHaveCount(2);
});
