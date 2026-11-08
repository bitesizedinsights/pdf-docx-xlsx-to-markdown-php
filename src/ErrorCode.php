<?php

declare(strict_types=1);

namespace DocumentToMarkdown;

/** Short reason for a failed document. */
enum ErrorCode: string
{
    case BlockedUrl = 'blocked_url';
    case DownloadFailed = 'download_failed';
    case TooLarge = 'too_large';
    case UnsupportedFormat = 'unsupported_format';
    case InvalidDocument = 'invalid_document';
    case TooManyPages = 'too_many_pages';
    case ConversionFailed = 'conversion_failed';
    case ServiceUnavailable = 'service_unavailable';
}
