# Changelog

All notable changes to this project are documented in this file. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to [Semantic Versioning](https://semver.org/).

## [0.1.0] - 2026-11-08

### Added

- `Client::convert()` for converting PDF, Word, PowerPoint, Excel, HTML and image documents to Markdown with OCR, table, fast mode, chunk size and page limit options.
- Typed DTOs `ConvertedDocument`, `Page`, `Table` and `Chunk`.
- Enums `DocumentStatus`, `DocumentFormat`, `PageMethod` and `ErrorCode`.
- `ApiException` and `RateLimitException`.
- Injectable Guzzle client for testing.
