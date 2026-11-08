<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use DocumentToMarkdown\Client;

$apiToken = getenv('APIFY_API_TOKEN') ?: getenv('APIFY_TOKEN') ?: 'your_apify_api_token';

// Fetch a public PDF once and keep it in the temp directory.
// Here: "Attention Is All You Need" (arXiv, the Actor's prefill document)
$sourceUrl = 'https://arxiv.org/pdf/1706.03762';
$localFile = sys_get_temp_dir().'/document-to-markdown-example-'.md5($sourceUrl).'.pdf';

if (! is_file($localFile) || filesize($localFile) === 0) {
    $context = stream_context_create([
        'http' => ['timeout' => 60, 'user_agent' => 'pdf-docx-xlsx-to-markdown-php-example'],
    ]);
    $data = file_get_contents($sourceUrl, false, $context);

    if ($data === false || $data === '') {
        exit("Could not download {$sourceUrl}\n");
    }

    file_put_contents($localFile, $data);
}

printf("Local copy: %s (%d KB)\n", $localFile, filesize($localFile) / 1024);

// The Actor reads public URLs, so send the source URL rather than the local file
$client = new Client($apiToken);
[$document] = $client->convert([$sourceUrl], maxPagesPerDocument: 2);

if ($document->isFailed()) {
    exit("Failed ({$document->errorCode}): {$document->error}\n");
}

foreach ($document->pages as $page) {
    printf("--- Page %d (%s) ---\n%s\n", $page->page, $page->method ?? 'n/a', mb_substr($page->text, 0, 300));
}
