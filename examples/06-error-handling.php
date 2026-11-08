<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use DocumentToMarkdown\Client;
use DocumentToMarkdown\Exception\ApiException;
use DocumentToMarkdown\Exception\RateLimitException;

$apiToken = getenv('APIFY_API_TOKEN') ?: getenv('APIFY_TOKEN') ?: 'your_apify_api_token';

$client = (new Client)->setApifyKey($apiToken);

try {
    $documents = $client->convert([
        'https://arxiv.org/pdf/1706.03762',
        'https://example.com/notes.txt',
    ], maxPagesPerDocument: 2);
} catch (RateLimitException $e) {
    // Wait the advised time before retrying
    sleep($e->retryAfter);

    exit("Rate limited, retry later\n");
} catch (ApiException $e) {
    exit("API error: {$e->getMessage()}\n");
}

// A single bad document does not throw: it comes back as a failed result
foreach ($documents as $document) {
    if ($document->isFailed()) {
        echo "{$document->url}: failed ({$document->errorCode}) {$document->error}\n";

        continue;
    }

    if ($document->isPartial()) {
        echo "{$document->url}: partial, {$document->pagesConverted} pages converted\n";

        continue;
    }

    echo "{$document->url}: {$document->pagesConverted} pages, {$document->getTableCount()} tables\n";
}
