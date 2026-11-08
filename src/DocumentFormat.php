<?php

declare(strict_types=1);

namespace DocumentToMarkdown;

/** Document type detected by the Actor. */
enum DocumentFormat: string
{
    case Pdf = 'pdf';
    case Docx = 'docx';
    case Pptx = 'pptx';
    case Xlsx = 'xlsx';
    case Html = 'html';
    case Image = 'image';
}
