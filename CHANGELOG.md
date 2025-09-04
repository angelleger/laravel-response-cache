# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]
- Switched to key-based caching strategy with deterministic keys including method, path, query, locale and auth guard
- Added helpers `makeKey`, `rememberResponse`, and `forgetByKey`
- New middleware options and configuration for vary headers/cookies and query filtering
- Added Artisan commands `response-cache:clear` and `response-cache:stats`
- Introduced optional atomic locks and configuration for stampede protection
- Broadened Pint constraint to install on PHP 8.1 environments
