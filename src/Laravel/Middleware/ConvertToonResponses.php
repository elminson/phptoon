<?php

namespace PhpToon\Laravel\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use PhpToon\ToonEncoder;
use Symfony\Component\HttpFoundation\Response;

class ConvertToonResponses
{
    /**
     * Handle an outgoing response and convert to TOON if requested
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldConvertToToon($request, $response)) {
            $data = $this->getResponseData($response);
            $toon = ToonEncoder::encode($data);

            return response($toon, $response->getStatusCode())
                ->header('Content-Type', 'text/toon; charset=utf-8');
        }

        return $response;
    }

    /**
     * Check if response should be converted to TOON
     */
    private function shouldConvertToToon(Request $request, Response $response): bool
    {
        // Check Accept header
        $accept = $request->header('Accept', '');
        if (str_contains($accept, 'text/toon') || str_contains($accept, 'application/toon')) {
            return true;
        }

        // Check for query parameter
        if ($request->query('format') === 'toon') {
            return true;
        }

        return false;
    }

    /**
     * Extract data from response
     */
    private function getResponseData(Response $response): mixed
    {
        if ($response instanceof JsonResponse) {
            return $response->getData(true);
        }

        $content = $response->getContent();
        $decoded = json_decode($content, true);

        return $decoded ?? $content;
    }
}
