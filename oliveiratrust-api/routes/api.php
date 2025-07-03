<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{
    FileUploadController,
    FileHistoryController,
    FinancialDataController
};

Route::middleware(['api', 'throttle:60,1'])->group(function () {
    Route::post('/upload', [FileUploadController::class, 'upload']);
    Route::get('/uploads', [FileHistoryController::class, 'index']);
    Route::get('/financial-data/search', [FinancialDataController::class, 'search']);
});
