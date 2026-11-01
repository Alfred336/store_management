# Agribusiness Store Management System

Desktop-first stock management web application built with Laravel 13, Livewire 4, Flux UI Free, Tailwind CSS, and PostgreSQL. It is intended for deployment behind Nginx on a Linux VPS.

## Requirements

- PHP 8.3 or newer.
- PostgreSQL 14 or newer. PostgreSQL 14 is the minimum supported project baseline and is used by CI. The application intentionally relies on PostgreSQL features including partial indexes, `ILIKE`, row locks, and check constraints.
- Required PHP extensions: `pdo_pgsql`, `bcmath`, `mbstring`, `zip`, and `intl`.
- `gd` or `imagick` for image handling and image content in PDFs; GD is the extension used in CI.
- Node.js 22 or newer and npm for compiling Vite assets.
- SQLite is not supported.

## Installation

See [docs/INSTALLATION.md](docs/INSTALLATION.md) for environment setup, first administrator creation, and test-database safeguards.