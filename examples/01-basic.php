<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use DocumentToMarkdown\Client;

$apiToken = getenv('APIFY_API_TOKEN') ?: getenv('APIFY_TOKEN') ?: 'your_apify_api_token';

$client = (new Client)->setApifyKey($apiToken);

[$document] = $client->convert(['https://arxiv.org/pdf/1706.03762'], maxPagesPerDocument: 3);

if ($document->isFailed()) {
    exit("Failed ({$document->errorCode}): {$document->error}\n");
}

printf(
    "%s: %d of %s pages converted\n\n",
    $document->title ?? $document->url,
    $document->pagesConverted,
    $document->totalPages ?? '?',
);

echo mb_substr((string) $document->markdown, 0, 800)."\n";
