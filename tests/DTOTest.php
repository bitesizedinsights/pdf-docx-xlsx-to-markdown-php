<?php

declare(strict_types=1);

use DocumentToMarkdown\DocumentFormat;
use DocumentToMarkdown\DocumentStatus;
use DocumentToMarkdown\DTO\Chunk;
use DocumentToMarkdown\DTO\ConvertedDocument;
use DocumentToMarkdown\DTO\Page;
use DocumentToMarkdown\DTO\Table;
use DocumentToMarkdown\ErrorCode;
use DocumentToMarkdown\PageMethod;

test('fromArray maps a full document', function () {
    $doc = ConvertedDocument::fromArray(loadFixture('document_full'));

    expect($doc->url)->toBe('https://example.com/report.pdf')
        ->and($doc->status)->toBe(DocumentStatus::Success)
        ->and($doc->format)->toBe('pdf')
        ->and($doc->title)->toBe('Quarterly Report')
        ->and($doc->totalPages)->toBe(12)
        ->and($doc->pagesConverted)->toBe(2)
        ->and($doc->ocrPages)->toBe(1)
        ->and($doc->truncated)->toBeTrue()
        ->and($doc->markdown)->toStartWith('# Quarterly Report')
        ->and($doc->pages)->toHaveCount(2)
        ->and($doc->pages[0])->toBeInstanceOf(Page::class)
        ->and($doc->pages[1]->ocr)->toBeTrue()
        ->and($doc->tables[0])->toBeInstanceOf(Table::class)
        ->and($doc->tables[0]->page)->toBe(3)
        ->and($doc->tables[0]->rows[1])->toBe(['EMEA', '4.1M'])
        ->and($doc->chunks[0])->toBeInstanceOf(Chunk::class)
        ->and($doc->chunks[0]->pages)->toBe([1, 2])
        ->and($doc->chunks[0]->heading)->toBe('Quarterly Report')
        ->and($doc->chunks[0]->tokens)->toBe(480)
        ->and($doc->errorCode)->toBeNull()
        ->and($doc->error)->toBeNull();
});

test('fromArray maps a document with required fields only', function () {
    $doc = ConvertedDocument::fromArray(loadFixture('document_minimal'));

    expect($doc->format)->toBeNull()
        ->and($doc->title)->toBeNull()
        ->and($doc->totalPages)->toBeNull()
        ->and($doc->markdown)->toBeNull()
        ->and($doc->pages[0]->method)->toBeNull()
        ->and($doc->pages[0]->getMethod())->toBeNull()
        ->and($doc->tables)->toBe([])
        ->and($doc->chunks)->toBe([]);
});

test('fromArray maps a failed document', function () {
    $doc = ConvertedDocument::fromArray(loadFixture('document_failed'));

    expect($doc->isFailed())->toBeTrue()
        ->and($doc->isSuccessful())->toBeFalse()
        ->and($doc->isPartial())->toBeFalse()
        ->and($doc->getErrorCode())->toBe(ErrorCode::UnsupportedFormat)
        ->and($doc->getFormat())->toBeNull()
        ->and($doc->error)->toContain('Unsupported');
});

test('status helpers', function () {
    $partial = ConvertedDocument::fromArray([...loadFixture('document_full'), 'status' => 'partial']);

    expect($partial->isPartial())->toBeTrue()
        ->and($partial->isSuccessful())->toBeFalse()
        ->and(ConvertedDocument::fromArray(loadFixture('document_full'))->isSuccessful())->toBeTrue();
});

test('format and error code helpers tolerate unknown values', function () {
    $doc = ConvertedDocument::fromArray([
        ...loadFixture('document_full'),
        'format' => 'epub',
        'error_code' => 'something_new',
    ]);

    expect($doc->format)->toBe('epub')
        ->and($doc->getFormat())->toBeNull()
        ->and($doc->errorCode)->toBe('something_new')
        ->and($doc->getErrorCode())->toBeNull()
        ->and(ConvertedDocument::fromArray(loadFixture('document_full'))->getFormat())->toBe(DocumentFormat::Pdf);
});

test('page text, table and chunk helpers', function () {
    $doc = ConvertedDocument::fromArray(loadFixture('document_full'));

    expect($doc->getPageText(1))->toStartWith('# Quarterly Report')
        ->and($doc->getPageText(9))->toBeNull()
        ->and($doc->getTableCount())->toBe(1)
        ->and($doc->getChunkCount())->toBe(1)
        ->and($doc->pages[0]->getMethod())->toBe(PageMethod::Fast)
        ->and($doc->pages[1]->getMethod())->toBe(PageMethod::Ocr);
});

test('table dimensions', function () {
    $table = Table::fromArray(['index' => 0, 'page' => null, 'markdown' => '', 'rows' => [['a'], ['b', 'c']]]);
    $empty = Table::fromArray(['index' => 1, 'page' => null, 'markdown' => '', 'rows' => []]);

    expect($table->page)->toBeNull()
        ->and($table->getRowCount())->toBe(2)
        ->and($table->getColumnCount())->toBe(2)
        ->and($empty->getRowCount())->toBe(0)
        ->and($empty->getColumnCount())->toBe(0);
});

test('invalid data throws InvalidArgumentException', function (array $override, string $message) {
    expect(fn () => ConvertedDocument::fromArray([...loadFixture('document_full'), ...$override]))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    'unknown status' => [['status' => 'weird'], "Unknown status 'weird'"],
    'missing url' => [['url' => null], "Field 'url'"],
    'bad pages count' => [['pages_converted' => 'x'], "Field 'pages_converted'"],
    'pages not objects' => [['pages' => ['x']], "Field 'pages' must contain objects"],
    'pages not array' => [['pages' => null], "Field 'pages' must be an array"],
    'table rows not arrays' => [['tables' => [['index' => 0, 'page' => 1, 'markdown' => '', 'rows' => ['x']]]],
        "Field 'rows' must contain arrays"],
    'table cells not strings' => [['tables' => [['index' => 0, 'page' => 1, 'markdown' => '', 'rows' => [[1]]]]],
        "Field 'rows' must contain strings"],
    'chunk pages not ints' => [['chunks' => [['index' => 0, 'text' => '', 'pages' => ['a'], 'heading' => null,
        'tokens' => 1]]], "Field 'pages' must contain integers"],
]);
