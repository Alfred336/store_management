<?php

namespace App\Enums;

/** Available invoice print templates with their supported paper sizes. */
enum InvoiceTemplate: string
{
    case Classic = 'classic';
    case Modern = 'modern';
    case Minimal = 'minimal';
    case Compact = 'compact';
    case Thermal = 'thermal';

    public function label(): string
    {
        return match ($this) {
            self::Classic => 'Classic',
            self::Modern => 'Modern',
            self::Minimal => 'Minimal',
            self::Compact => 'Compact (A5)',
            self::Thermal => 'Thermal receipt',
        };
    }

    /** Paper sizes this template supports.
     *
     * @return list<string>
     */
    public function supportedPapers(): array
    {
        return match ($this) {
            self::Thermal => ['80mm', '58mm'],
            self::Compact => ['A4', 'A5'],
            default => ['A4'],
        };
    }

    public function defaultPaper(): string
    {
        return match ($this) {
            self::Thermal => '80mm',
            self::Compact => 'A5',
            default => 'A4',
        };
    }

    public function isThermal(): bool
    {
        return $this === self::Thermal;
    }

    /** All paper sizes across every template (for validation).
     *
     * @return list<string>
     */
    public static function allPapers(): array
    {
        return ['A4', 'A5', '80mm', '58mm'];
    }

    /** Default template for the application. */
    public static function default(): self
    {
        return self::Classic;
    }
}
