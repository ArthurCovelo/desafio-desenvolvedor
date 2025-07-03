<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IncreaseUploadLimit
{

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/upload') || $request->is('api/uploads')) {

            ini_set('upload_max_filesize', '50M');
            ini_set('post_max_size', '50M');
            ini_set('max_input_time', 300);
            ini_set('max_execution_time', 300);

            if ($request->getContent() > 50 * 1024 * 1024) {
                return response()->json([
                    'error' => 'File size exceeds the maximum allowed limit of 50MB'
                ], 413);
            }
        }

        return $next($request);
    }
}
