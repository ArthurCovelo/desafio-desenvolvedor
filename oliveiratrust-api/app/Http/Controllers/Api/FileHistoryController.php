<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FileUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FileHistoryController extends Controller
{

    public function index(Request $request)
    {
        Log::channel('api')->info('Consulta de histórico de uploads', $request->query());

        try {
            $query = FileUpload::query()
                ->select([
                    'id',
                    'original_name',
                    'status',
                    'total_records',
                    'created_at',
                    'updated_at'
                ])
                ->latest();

            if ($search = $request->query('filename')) {
                $query->where('original_name', 'ilike', "%{$search}%");
            }

            if ($date = $request->query('date')) {
                $query->whereDate('created_at', $date);
            }

            $uploads = $query->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $uploads->items(),
                'meta' => [
                    'current_page' => $uploads->currentPage(),
                    'per_page' => $uploads->perPage(),
                    'total' => $uploads->total(),
                    'last_page' => $uploads->lastPage()
                ]
            ]);

        } catch (\Exception $e) {
            Log::channel('api')->error('Erro ao consultar histórico', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar histórico de uploads'
            ], 500);
        }
    }
}
