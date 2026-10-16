<?php

namespace App\Providers;

use App\Services\AI\AiException;
use App\Services\AI\AiProviderInterface;
use App\Services\AI\MockProvider;
use App\Services\AI\OpenAiProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AiProviderInterface::class, fn ($app) => match (config('writing.provider')) {
            'openai' => $app->make(OpenAiProvider::class), 'mock' => $app->make(MockProvider::class),
            default => throw new AiException('Unknown AI_PROVIDER. Choose openai or mock.', 503),
        });
    }

    public function boot(): void
    {
        RateLimiter::for('writing', fn () => Limit::perMinute(config('writing.rate_limit'))->by('local-user'));
        RateLimiter::for('local-api', fn () => Limit::perMinute(180)->by('local-user'));
    }
}
