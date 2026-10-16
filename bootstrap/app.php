<?php

use App\Http\Middleware\LocalOnly;
use App\Services\AI\AiException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(LocalOnly::class);
        $middleware->convertEmptyStringsToNull(except: [fn (Request $r) => $r->is('api/*')]);
        $middleware->trimStrings(except: [fn (Request $r) => $r->is('api/*')]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $r) => $r->is('api/*') || $r->expectsJson());
        $exceptions->dontReport([AiException::class]);
        $exceptions->render(fn (AiException $e) => response()->json(['message' => $e->getMessage()], $e->status));
    })->create();
