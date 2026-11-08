<?php

declare(strict_types=1);

namespace DocumentToMarkdown\DTO;

use DocumentToMarkdown\Support\Value;
use InvalidArgumentException;

final readonly class Chunk
{
    /** @param  list<int>  $pages  Source pages of the chunk. */
    public function __construct(
        public int $index,
        public string $text,
        public array $pages,
        public ?string $heading,
        public int $tokens,
    ) {}

    /** @param  array<mixed>  $data */
    public static function fromArray(array $data): self
    {
        $pages = [];
        foreach (Value::array($data, 'pages') as $page) {
            if (! is_int($page)) {
                throw new InvalidArgumentException("Field 'pages' must contain integers");
            }

            $pages[] = $page;
        }

        return new self(
            index: Value::int($data, 'index'),
            text: Value::string($data, 'text'),
            pages: $pages,
            heading: Value::nullableString($data, 'heading'),
            tokens: Value::int($data, 'tokens'),
        );
    }
}
