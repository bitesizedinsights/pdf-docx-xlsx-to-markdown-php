<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use DocumentToMarkdown\Client;

$apiToken = getenv('APIFY_API_TOKEN') ?: getenv('APIFY_TOKEN') ?: 'your_apify_api_token';

$client = new Client($apiToken);

// Split each document into chunks of about 512 tokens, ready for embeddings
[$document] = $client->convert(['https://arxiv.org/pdf/1706.03762'], chunkSize: 512, maxPagesPerDocument: 5);

foreach ($document->chunks as $chunk) {
    printf(
        "#%d  pages %s  %d tokens  heading: %s\n",
        $chunk->index,
        implode(',', $chunk->pages),
        $chunk->tokens,
        $chunk->heading ?? '-',
    );
}

// Store $chunk->text with $chunk->pages and $chunk->heading as metadata in your vector database
