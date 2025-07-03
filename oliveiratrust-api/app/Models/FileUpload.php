<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FileUpload extends Model
{
    protected $fillable = [
        'original_name',
        'stored_name',
        'file_hash',
        'status',
        'reference_date',
        'total_records',
    ];

    public function financialData()
    {
        return $this->hasMany(FinancialData::class);
    }

    public function getExtension(): string
    {
        return pathinfo($this->stored_name, PATHINFO_EXTENSION);
    }
}
