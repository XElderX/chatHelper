<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class LocalOnly
{
    public function handle(Request $request, Closure $next)
    {
        // No trusted proxy configuration: inspect actual peer, not forwarded headers.
        abort_unless(in_array($request->server('REMOTE_ADDR'), ['127.0.0.1', '::1'], true)
            && in_array($request->getHost(), ['127.0.0.1', 'localhost', '[::1]'], true), 403, 'chatHelper is restricted to this computer.');
        if ($origin = $request->header('Origin')) {
            abort_unless($origin === $request->getSchemeAndHttpHost(), 403, 'Cross-origin requests are not allowed.');
        }
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
