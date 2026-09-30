# Instagram Slider Widget

## Overview

Instagram Slider Widget is a WordPress plugin. Its primary PHP code is in the repository root and `includes/`; `libs/` contains bundled libraries, and `bin/` contains distribution tooling.

## Environment

The Copilot setup workflow has already installed the locked Composer and npm dependencies. Do not rerun `composer install` or `npm ci` unless the task changes their manifests or lock files.

The project supports PHP 7.4 and later.

## Validation

This repository has no existing automated unit, lint, or E2E test command. Do not create or run an E2E suite unless the task introduces one. For distribution work, use the existing `npm run dist` script only when generated artifacts are required.

## Conventions

- Keep WordPress-facing code compatible with PHP 7.4.
- Follow the surrounding WordPress coding style.
- Treat `libs/` as bundled third-party code; change it only when the task specifically requires it.
- Do not commit generated distribution artifacts unless the task explicitly requires them.
