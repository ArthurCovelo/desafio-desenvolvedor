<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialData;
use Illuminate\Http\Request;

class FinancialDataController extends Controller
{
    public function search(Request $request)
    {
        $query = FinancialData::query()->orderByDesc('report_date');

        if ($request->filled('TckrSymb')) {
            $query->where('ticker_symbol', $request->input('TckrSymb'));
        }

        if ($request->filled('RptDt')) {
            $query->whereDate('report_date', $request->input('RptDt'));
        }

        if ($request->filled('ISIN')) {
            $query->where('isin', $request->input('ISIN'));
        }

        if ($request->filled('CrpnNm')) {
            $query->where('corporate_name', 'ilike', '%' . $request->input('CrpnNm') . '%');
        }

        $perPage = $request->input('per_page', 20);
        $results = $query->paginate($perPage);

        $data = $results->map(function ($item) {
            return [
                'RptDt'        => $item->report_date,
                'TckrSymb'     => $item->ticker_symbol,
                'MktNm'        => $item->market_name,
                'SctyCtgyNm'   => $item->security_category,
                'ISIN'         => $item->isin,
                'CrpnNm'       => $item->corporate_name,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $data,
            'meta'    => [
                'current_page' => $results->currentPage(),
                'per_page'     => $results->perPage(),
                'total'        => $results->total(),
                'last_page'    => $results->lastPage(),
            ]
        ]);
    }
}
