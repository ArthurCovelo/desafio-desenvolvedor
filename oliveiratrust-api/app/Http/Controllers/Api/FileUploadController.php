<?php

namespace App\Http\Controllers\Api;

use App\Jobs\ProcessFinancialFile;
use App\Models\FileUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class FileUploadController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimetypes:text/csv,text/plain,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();

        if (FileUpload::where('original_name', $originalName)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Arquivo já enviado anteriormente.',
            ], 422);
        }

        $storedName = Str::random(20) . '.' . $file->getClientOriginalExtension();
      $path = $file->storeAs('uploads', $storedName);
      Log::channel('processing')->info("Arquivo armazenado em: " . storage_path('app/' . $path));

        $fileHash = md5_file($file->getRealPath());

        $fileUpload = FileUpload::create([
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'file_hash' => $fileHash,
            'status' => 'uploaded',
            'reference_date' => null,
        ]);

        ProcessFinancialFile::dispatch($fileUpload)->onQueue('file_processing');

        return response()->json([
            'success' => true,
            'message' => 'Arquivo enviado e processamento iniciado.',
            'data' => $fileUpload,
        ]);
    }

    public function history(Request $request)
    {
        $query = FileUpload::query();

        if ($request->filled('filename')) {
            $query->where('original_name', 'like', '%' . $request->filename . '%');
        }

        if ($request->filled('reference_date')) {
            $query->whereDate('reference_date', $request->reference_date);
        }

        $uploads = $query->orderBy('created_at', 'desc')->paginate(10);

        return response()->json($uploads);
    }

    public function search(Request $request)
    {
        $query = \App\Models\FinancialData::query();

        if ($request->filled('TckrSymb')) {
            $query->where('ticker_symbol', $request->input('TckrSymb'));
        }

        if ($request->filled('RptDt')) {
            $query->where('report_date', $request->input('RptDt'));
        }

        $result = $query->select([
            'report_date as RptDt',
            'ticker_symbol as TckrSymb',
            'market_name as MktNm',
            'security_category as SctyCtgyNm',
            'isin as ISIN',
            'corporate_name as CrpnNm',
        ])->paginate(20);

        return response()->json($result);
    }
}
