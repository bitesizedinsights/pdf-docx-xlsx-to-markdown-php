<?php

declare(strict_types=1);

namespace DocumentToMarkdown;

/** Outcome of one input document. */
enum DocumentStatus: string
{
    case Success = 'success';
    case Partial = 'partial';
    case Failed = 'failed';
}
