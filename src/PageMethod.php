<?php

declare(strict_types=1);

namespace DocumentToMarkdown;

/** How a page was converted. Informational, never affects the price. */
enum PageMethod: string
{
    case Fast = 'fast';
    case Layout = 'layout';
    case Ocr = 'ocr';
    case Native = 'native';
}
