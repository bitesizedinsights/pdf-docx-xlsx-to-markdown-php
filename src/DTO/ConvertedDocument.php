<?php

declare(strict_types=1);

namespace DocumentToMarkdown\DTO;

use DocumentToMarkdown\DocumentFormat;
use DocumentToMarkdown\DocumentStatus;
use DocumentToMarkdown\ErrorCode;
use DocumentToMarkdown\Support\Value;
use InvalidArgumentException;

final readonly class ConvertedDocument
{
    /**
     * @param  list<Page>  $pages
     * @param  list<Table>  $tables
     * @param  list<Chunk>  $chunks
     */
    public function __construct(
        public string $url,
        public DocumentStatus $status,
        public int $pagesConverted,
        public int $ocrPages,
        public bool $truncated,
        public array $pages,
        public array $tables,
        public array $chunks,
        public ?string $format = null,
        public ?string $title = null,
        public ?int $totalPages = null,
        public ?string $markdown = null,
        public ?string $errorCode = null,
        public ?string $error = null,
    ) {}

    /**
     * @param  array<mixed>  $data
     *
     * @throws InvalidArgumentException when a required field is missing or has the wrong type
     */
    public static function fromArray(array $data): self
    {
        $statusValue = Value::string($data, 'status');
        $status = DocumentStatus::tryFrom($statusValue)
            ?? throw new InvalidArgumentException("Unknown status '{$statusValue}'");

        return new self(
            url: Value::string($data, 'url'),
            status: $status,
            pagesConverted: Value::int($data, 'pages_converted'),
            ocrPages: Value::int($data, 'ocr_pages'),
            truncated: Value::bool($data, 'truncated'),
            pages: self::list($data, 'pages', Page::fromArray(...)),
            tables: self::list($data, 'tables', Table::fromArray(...)),
            chunks: self::list($data, 'chunks', Chunk::fromArray(...)),
            format: Value::nullableString($data, 'format'),
            title: Value::nullableString($data, 'title'),
            totalPages: Value::nullableInt($data, 'total_pages'),
            markdown: Value::nullableString($data, 'markdown'),
            errorCode: Value::nullableString($data, 'error_code'),
            error: Value::nullableString($data, 'error'),
        );
    }

    /**
     * @template T of object
     *
     * @param  array<mixed>  $data
     * @param  callable(array<mixed>): T  $factory
     * @return list<T>
     */
    private static function list(array $data, string $key, callable $factory): array
    {
        $items = [];
        foreach (Value::array($data, $key) as $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException("Field '{$key}' must contain objects");
            }

            $items[] = $factory($item);
        }

        return $items;
    }

    public function isSuccessful(): bool
    {
        return $this->status === DocumentStatus::Success;
    }

    public function isPartial(): bool
    {
        return $this->status === DocumentStatus::Partial;
    }

    public function isFailed(): bool
    {
        return $this->status === DocumentStatus::Failed;
    }

    /** The detected type as enum, or null when unknown or not known to this library. */
    public function getFormat(): ?DocumentFormat
    {
        return $this->format === null ? null : DocumentFormat::tryFrom($this->format);
    }

    /** The failure reason as enum, or null when the document did not fail or the code is not known to this library. */
    public function getErrorCode(): ?ErrorCode
    {
        return $this->errorCode === null ? null : ErrorCode::tryFrom($this->errorCode);
    }

    /** Markdown of one page (1-based), or null when that page is not in the result. */
    public function getPageText(int $page): ?string
    {
        foreach ($this->pages as $item) {
            if ($item->page === $page) {
                return $item->text;
            }
        }

        return null;
    }

    public function getTableCount(): int
    {
        return count($this->tables);
    }

    public function getChunkCount(): int
    {
        return count($this->chunks);
    }
}
