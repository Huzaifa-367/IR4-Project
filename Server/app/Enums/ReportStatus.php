<?php

namespace App\Enums;

enum ReportStatus: string
{
    case Draft = 'draft';
    case Generated = 'generated';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Generated => 'Generated',
            self::Published => 'Published',
        };
    }
}
