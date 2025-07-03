<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialData extends Model
{
    protected $fillable = [
        'report_date',
        'ticker_symbol',
        'isin',
        'corporate_name',
        'market_name',
        'security_category',
        'file_upload_id',
    ];
}
