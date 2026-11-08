<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use DocumentToMarkdown\Client;

$apiToken = getenv('APIFY_API_TOKEN') ?: getenv('APIFY_TOKEN') ?: 'your_apify_api_token';

$client = (new Client)->setApifyKey($apiToken);

// Tables come back as Markdown and as rows of cells, with the page they were found on
[$document] = $client->convert(['https://arxiv.org/pdf/1706.03762'], extractTables: true, maxPagesPerDocument: 10);

foreach ($document->tables as $table) {
    printf(
        "Table %d on page %s: %d rows x %d columns\n",
        $table->index,
        $table->page ?? '?',
        $table->getRowCount(),
        $table->getColumnCount(),
    );
}

if ($document->tables !== []) {
    echo "\n".$document->tables[0]->markdown."\n";
}
