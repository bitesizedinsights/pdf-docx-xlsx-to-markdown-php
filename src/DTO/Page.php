<?php

declare(strict_types=1);

namespace DocumentToMarkdown\DTO;

use DocumentToMarkdown\PageMethod;
use DocumentToMarkdown\Support\Value;

final readonly class Page
{
    public function __construct(
        public int $page,
        public string $text,
        public bool $ocr,
        public ?string $method = null,
    ) {}

    /** @param  array<mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            page: Value::int($data, 'page'),
            text: Value::string($data, 'text'),
            ocr: Value::bool($data, 'ocr'),
            method: Value::nullableString($data, 'method'),
        );
    }

    /** The conversion method as enum, or null when absent or not known to this library. */
    public function getMethod(): ?PageMethod
    {
        return $this->method === null ? null : PageMethod::tryFrom($this->method);
    }
}
