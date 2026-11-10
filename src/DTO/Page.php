<?php

declare(strict_types=1);

namespace DocumentToMarkdown\DTO;

use DocumentToMarkdown\PageMethod;
use DocumentToMarkdown\Support\Value;
use JsonSerializable;

final readonly class Page implements JsonSerializable
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

    /**
     * The result in the Actor's field names; {@see self::fromArray()} restores it.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'page' => $this->page,
            'text' => $this->text,
            'ocr' => $this->ocr,
            'method' => $this->method,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** The conversion method as enum, or null when absent or not known to this library. */
    public function getMethod(): ?PageMethod
    {
        return $this->method === null ? null : PageMethod::tryFrom($this->method);
    }
}
