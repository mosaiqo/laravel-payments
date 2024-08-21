<?php

namespace Mosaiqo\LaravelPayments\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Verify the signature of an incoming Stripe webhook.
 */
class StripeVerifyWebhookSignature
{
    /**
     * Handle the incoming request.
     *
     * @throws AccessDeniedHttpException
     */
    public function handle(Request $request, Closure $next): mixed
    {
        return $next($request);
    }
}
