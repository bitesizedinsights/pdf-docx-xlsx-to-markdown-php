<?php

declare(strict_types=1);

namespace DocumentToMarkdown\DTO;

use DocumentToMarkdown\Support\Value;
use InvalidArgumentException;

final readonly class Table
{
    /** @param  list<list<string>>  $rows */
    public function __construct(
        public int $index,
        public ?int $page,
        public string $markdown,
        public array $rows,
    ) {}

    /** @param  array<mixed>  $data */
    public static function fromArray(array $data): self
    {
        $rows = [];
        foreach (Value::array($data, 'rows') as $row) {
            if (! is_array($row)) {
                throw new InvalidArgumentException("Field 'rows' must contain arrays");
            }

            $cells = [];
            foreach ($row as $cell) {
                if (! is_string($cell)) {
                    throw new InvalidArgumentException("Field 'rows' must contain strings");
                }

                $cells[] = $cell;
            }

            $rows[] = $cells;
        }

        return new self(
            index: Value::int($data, 'index'),
            page: Value::nullableInt($data, 'page'),
            markdown: Value::string($data, 'markdown'),
            rows: $rows,
        );
    }

    public function getRowCount(): int
    {
        return count($this->rows);
    }

    /** Number of cells in the widest row. */
    public function getColumnCount(): int
    {
        return $this->rows === [] ? 0 : max(array_map('count', $this->rows));
    }
}
