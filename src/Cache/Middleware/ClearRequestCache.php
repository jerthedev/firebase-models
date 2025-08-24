<?php

namespace JTD\FirebaseModels\Cache\Middleware;

use Closure;
use Illuminate\Http\Request;
use JTD\FirebaseModels\Cache\RequestCache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to clear request cache between requests.
 *
 * This middleware ensures that the request-scoped cache is cleared
 * at the beginning of each request, preventing cache pollution
 * between different HTTP requests.
 */
class ClearRequestCache
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Clear the request cache at the start of each request
        RequestCache::clear();

        // Reset statistics for the new request
        RequestCache::resetStats();

        return $next($request);
    }

    /**
     * Handle tasks after the response has been sent to the browser.
     */
    public function terminate(Request $request, Response $response): void
    {
        // Log cache statistics if debugging is enabled
        if (config('firebase-models.cache.log_stats', false)) {
            $stats = RequestCache::getStats();

            if ($stats['hits'] + $stats['misses'] > 0) {
                \Log::debug('Request Cache Stats', $stats);
            }
        }

        // Clear cache after request is complete to free memory
        RequestCache::clear();
    }
}
