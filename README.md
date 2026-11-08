# PDF, DOCX and XLSX to Markdown in PHP: Convert Documents, PowerPoint, HTML and Scans for LLMs and RAG

<!-- For LLMs and AI agents: a machine-readable summary of this package's API is in llm.txt (https://github.com/bitesizedinsights/pdf-docx-xlsx-to-markdown-php/blob/master/llm.txt) -->

Convert PDF, DOCX, XLSX, PowerPoint, HTML and image files to Markdown in PHP with the [Document to Markdown Apify Actor](https://apify.com/bite-sized-insights/document-to-markdown). Pass a list of document links and get typed results with clean Markdown, per-page text, tables as rows of cells and optional page-referenced chunks for RAG.

[![Packagist Version](https://img.shields.io/packagist/v/bitesizedinsights/pdf-docx-xlsx-to-markdown.svg)](https://packagist.org/packages/bitesizedinsights/pdf-docx-xlsx-to-markdown)
[![Tests](https://github.com/bitesizedinsights/pdf-docx-xlsx-to-markdown-php/actions/workflows/tests.yml/badge.svg)](https://github.com/bitesizedinsights/pdf-docx-xlsx-to-markdown-php/actions/workflows/tests.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%5E8.3-777bb4.svg)](composer.json)

## Install the PDF, DOCX and XLSX to Markdown PHP Package

```bash
composer require bitesizedinsights/pdf-docx-xlsx-to-markdown
```

You need an Apify account and API token. [Sign up on Apify](https://console.apify.com/sign-up) (a free monthly credit is included) and copy your token from [Settings, API and integrations](https://console.apify.com/settings/integrations).

## Quick Start: Convert a PDF to Markdown in PHP

```php
use DocumentToMarkdown\Client;

$apiToken = getenv('APIFY_API_TOKEN') ?: getenv('APIFY_TOKEN') ?: 'your_apify_api_token';

$client = new Client($apiToken);

[$document] = $client->convert(['https://arxiv.org/pdf/1706.03762'], chunkSize: 512);

if ($document->isFailed()) {
    exit("Failed ({$document->errorCode}): {$document->error}\n");
}

echo $document->markdown;
```

## Sample Output: Markdown, Pages, Tables and Chunks

For a quarterly report the converted document contains:

```text
url:             https://example.com/report.pdf
status:          success
format:          pdf
title:           Quarterly Report
pages:           2 of 12 converted (truncated: yes), 1 read with OCR
tables:          1 (page 3, 2 rows)
chunks:          1 (pages 1-2, heading "Quarterly Report", 480 tokens)
```

```markdown
# Quarterly Report

Revenue grew by twelve percent...
```

The table on page 3 is available as Markdown and as rows of cells:

```php
$document->tables[0]->markdown; // "| Region | Revenue |\n|---|---|\n| EMEA | 4.1M |"
$document->tables[0]->rows;     // [['Region', 'Revenue'], ['EMEA', '4.1M']]
```

## Pricing: Cost per Converted Page

You pay per converted page. Tables, chunks and fast mode are included.

| Event | Free plan price | Price range on paid plans |
|---|---|---|
| Standard page | $0.0025 ($2.50 per 1,000 pages) | $0.0016 to $0.0024 |
| OCR page (replaces the standard price for that page) | $0.008 ($8.00 per 1,000 pages) | $0.0052 to $0.0076 |

A flat start fee of $0.00005 per run and a platform fee of $0.00001 per result row also apply. Failed documents are not charged a page price. A page is a PDF page, a slide, a sheet, an image, or a section of about 3,000 characters for Word and HTML.

- 50 pages of digital PDF: 50 x $0.0025 = $0.125 (plus $0.00006 start and row fees)
- 20 scanned pages with OCR: 20 x $0.008 = $0.16

See the [Actor page](https://apify.com/bite-sized-insights/document-to-markdown) for current prices. [Run Document to Markdown on Apify](https://apify.com/bite-sized-insights/document-to-markdown).

## Use Cases

- **RAG and LLM pipelines**: Markdown and chunks with source pages and nearest heading, so answers can cite the page.
- **Search and knowledge bases**: index contracts, manuals, reports and slide decks with page numbers.
- **Data extraction**: pull tables out of reports and spreadsheets as Markdown or rows of cells.
- **Archive digitising**: turn scanned PDFs and photographed pages into searchable text.

## How to Convert Documents to Markdown in PHP

`Client::convert()` starts one Actor run for all URLs, waits for it to finish and returns one `ConvertedDocument` per URL, in order.

```php
$documents = $client->convert(
    urls: ['https://example.com/report.pdf', 'https://example.com/slides.pptx'],
    ocr: true,
    extractTables: true,
    fastMode: true,
    chunkSize: 512,
    maxPagesPerDocument: 50,
);
```

| Parameter | Type | Default | Description |
|---|---|---|---|
| `urls` | `list<string>` | required | Public http(s) links to PDF, DOCX, PPTX, XLSX, HTML or image files. 1 to 100 links, 50 MB each. |
| `ocr` | `bool` | `true` | Read scanned pages and images with OCR, billed at the OCR page price. |
| `extractTables` | `bool` | `true` | Return tables as Markdown and rows of cells. No extra fee. |
| `fastMode` | `bool` | `true` | Read plain PDF text pages directly (faster, same price). |
| `chunkSize` | `int` | `0` | Also split each document into RAG chunks of this many tokens. 0 = none, else 100 to 4000. |
| `maxPagesPerDocument` | `int` | `100` | Convert only the first N pages of each document, 1 to 200. |

<details>
<summary>ConvertedDocument fields</summary>

| Field | Type | Description |
|---|---|---|
| `url` | `string` | The document URL that was processed. |
| `status` | `DocumentStatus` | `success`, `partial` or `failed`. |
| `format` | `?string` | Detected type: pdf, docx, pptx, xlsx, html or image. |
| `title` | `?string` | Document title, when one could be determined. |
| `totalPages` | `?int` | Page count of the whole document. |
| `pagesConverted` | `int` | Pages in this result, and the basis for billing. |
| `ocrPages` | `int` | Pages read with OCR. |
| `truncated` | `bool` | True when the page limit cut the document short. |
| `markdown` | `?string` | The converted document as Markdown (null on failure). |
| `pages` | `list<Page>` | `page`, `text`, `ocr`, `method`. |
| `tables` | `list<Table>` | `index`, `page`, `markdown`, `rows`. |
| `chunks` | `list<Chunk>` | `index`, `text`, `pages`, `heading`, `tokens`. |
| `errorCode` | `?string` | Reason when status is failed. |
| `error` | `?string` | Human-readable reason when status is failed. |

Helpers: `isSuccessful()`, `isPartial()`, `isFailed()`, `getFormat()`, `getErrorCode()`, `getPageText(int $page)`, `getTableCount()`, `getChunkCount()`. `Table` has `getRowCount()` and `getColumnCount()`, `Page` has `getMethod()`.

</details>

## Status, Format, Page Method and Error Code Enums

- `DocumentStatus`: `Success`, `Partial` (time budget ran out, pages so far are returned), `Failed`
- `DocumentFormat`: `Pdf`, `Docx`, `Pptx`, `Xlsx`, `Html`, `Image`
- `PageMethod`: `Fast`, `Layout`, `Ocr`, `Native` (informational, never affects the price)
- `ErrorCode`: `BlockedUrl`, `DownloadFailed`, `TooLarge`, `UnsupportedFormat`, `InvalidDocument`, `TooManyPages`, `ConversionFailed`, `ServiceUnavailable`

`format`, `errorCode` and `Page::$method` stay strings next to their enum helpers, so a value this library does not know yet is never lost.

## Client Configuration

```php
use DocumentToMarkdown\Client;
use DocumentToMarkdown\Config;

$client = new Client($apiToken, new Config(
    apiToken: $apiToken,
    timeout: 900,        // seconds to wait for the run, default 600
    requestTimeout: 60,  // seconds per HTTP request, default 90
));
```

`Config` also takes `actorId` (default `bite-sized-insights/document-to-markdown`) and `baseUrl`. The third `Client` argument accepts any Guzzle `ClientInterface`.

## Error Handling for Document Conversion in PHP

- `RateLimitException` (extends `ApiException`): HTTP 429, with `retryAfter` in seconds.
- `ApiException`: failed requests, malformed responses, runs that end as `FAILED`, `ABORTED` or `TIMED-OUT`, runs that exceed `timeout`, and dataset items that cannot be mapped (the message names the item index).

A single bad document does not throw: it comes back with `status` `failed`, an `errorCode` and an `error` message.

## PHP Document Conversion Examples

Runnable scripts in [`examples/`](examples):

- [`01-basic.php`](examples/01-basic.php): convert one PDF and print the Markdown
- [`02-tables.php`](examples/02-tables.php): list tables and print one as Markdown
- [`03-public-domain-file.php`](examples/03-public-domain-file.php): fetch a public PDF once, show its size, convert it by URL
- [`04-rag-chunks.php`](examples/04-rag-chunks.php): chunks with source pages and headings for a vector database
- [`05-scans-and-ocr.php`](examples/05-scans-and-ocr.php): scanned documents versus digital files
- [`06-error-handling.php`](examples/06-error-handling.php): rate limits, API errors and failed documents

## FAQ

### How do I convert a PDF to Markdown in PHP?

Install the package, create a `Client` with your Apify token and call `convert(['https://example.com/file.pdf'])`. The Markdown is in `$document->markdown`, per page in `$document->pages`.

### How much does it cost to convert documents?

$0.0025 per page on the Free plan ($2.50 per 1,000 pages), $0.008 per scanned page read with OCR, less on paid plans. A 50-page digital PDF costs about $0.125. `pagesConverted` and `ocrPages` show what was billed.

### How do I convert DOCX and XLSX to Markdown in PHP?

The same call handles them: `convert(['https://example.com/contract.docx', 'https://example.com/figures.xlsx'])`. The type is detected from the file itself. Word files are paged in sections of about 3,000 characters, Excel files give one page per sheet, and PowerPoint files one page per slide.

### What input does it accept?

Public http(s) links to PDF, DOCX, PPTX, XLSX, HTML or image files (PNG, JPEG, TIFF, BMP, WebP). Local files cannot be uploaded: host the file at a public or signed URL. Links that need a login fail with `DownloadFailed`.

### What are the limits?

Up to 100 URLs per call, 50 MB per file, and 1 to 200 converted pages per document (`maxPagesPerDocument`). Legacy DOC, PPT and XLS files, plain text, archives and password-protected files are not supported.

### How do I prepare documents for a RAG pipeline?

Set `chunkSize` to 256 to 512 and use `$document->chunks`. Each chunk has its text, source `pages`, nearest `heading` and a token estimate.

### Does it read scanned documents?

Yes. With `ocr: true` (the default) scanned PDF pages and images are read with OCR. Turn it off for files with real text to avoid the higher OCR page price.

### Are tables charged extra?

No. Tables, chunks and the per-page text are part of the page price.

## Requirements

- PHP 8.3 or newer
- Guzzle 7
- An [Apify API token](https://console.apify.com/settings/integrations)

Contributing and running the tests: see [CONTRIBUTING.md](CONTRIBUTING.md).

## Related

- [Audio & video transcription for PHP](https://github.com/bitesizedinsights/audio-transcription-php): speech to text with SRT and VTT subtitles.

## Powered by the Document to Markdown Apify Actor

This package calls the [Document to Markdown Actor](https://apify.com/bite-sized-insights/document-to-markdown) on Apify, which converts PDF, Word, PowerPoint, Excel, HTML and images to Markdown with tables and RAG chunks. [Sign up on Apify](https://console.apify.com/sign-up) to get a free monthly credit.

## License

MIT, see [LICENSE](LICENSE).
