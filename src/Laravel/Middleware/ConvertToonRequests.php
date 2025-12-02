<?php

namespace PhpToon\Laravel\Middleware;

use Closure;
use Illuminate\Http\Request;
use PhpToon\ToonDecoder;
use PhpToon\Exceptions\ToonDecodeException;
use Symfony\Component\HttpFoundation\Response;

class ConvertToonRequests
{
    /**
     * Handle an incoming request with TOON content
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isToonRequest($request)) {
            try {
                $decoded = ToonDecoder::decode($request->getContent());
                $request->merge(is_array($decoded) ? $decoded : ['data' => $decoded]);
            } catch (ToonDecodeException $e) {
                return response()->json([
                    'error' => 'Invalid TOON format',
                    'message' => $e->getMessage(),
                ], 400);
            }
        }

        return $next($request);
    }

    /**
     * Check if request contains TOON content
     */
    private function isToonRequest(Request $request): bool
    {
        $contentType = $request->header('Content-Type', '');
        return str_contains($contentType, 'text/toon') ||
               str_contains($contentType, 'application/toon');
    }
}
