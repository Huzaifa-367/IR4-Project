<?php

namespace App\Models;

use App\Models\Concerns\HasCreatedBy;
use Database\Factories\EquipmentImportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class EquipmentImport extends Model
{
    /** @use HasFactory<EquipmentImportFactory> */
    use HasCreatedBy, HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'summary' => 'array',
        ];
    }
}
