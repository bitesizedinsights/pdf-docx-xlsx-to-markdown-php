<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use DocumentToMarkdown\Client;

$apiToken = getenv('APIFY_API_TOKEN') ?: getenv('APIFY_TOKEN') ?: 'your_apify_api_token';

$client = new Client($apiToken);

// Scanned pages and images are read with OCR (billed at the OCR page price)
[$scan] = $client->convert(['https://example.com/scanned-contract.pdf'], ocr: true);

// Digital files: switch OCR off so no page is billed at the OCR price
[$digital] = $client->convert(['https://example.com/annual-report.pdf'], ocr: false);

foreach ([$scan, $digital] as $document) {
    printf(
        "%s: %d pages converted, %d read with OCR\n",
        $document->url,
        $document->pagesConverted,
        $document->ocrPages,
    );
}
