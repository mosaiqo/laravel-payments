<?php

namespace Mosaiqo\LaravelPayments\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Mosaiqo\LaravelPayments\Events\WebhookHandled;
use Mosaiqo\LaravelPayments\Events\WebhookFailed;
use Mosaiqo\LaravelPayments\Events\WebhookReceived;
use Mosaiqo\LaravelPayments\Events\WebhookUnhandled;
use Mosaiqo\LaravelPayments\Exceptions\HandleEventMethodNotImplemented;
use Mosaiqo\LaravelPayments\Exceptions\InvalidEventName;
use Mosaiqo\LaravelPayments\Exceptions\InvalidCustomPayload;
use Mosaiqo\LaravelPayments\LaravelPayments;

use LemonSqueezy\Laravel\Http\Middleware\VerifyWebhookSignature;

final class PaymentsWebhookController extends Controller
{
    public function __construct()
    {
        if (LaravelPayments::shouldReturnNotFound()) {
            abort(404);
        }

        if (LaravelPayments::shouldInjectSignatureMiddleware()) {
            $this->middleware(LaravelPayments::resolveProviderSignatureMiddleware());
        }
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Foundation\Application|\Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory
     */
    public function __invoke(Request $request
    ): \Illuminate\Foundation\Application|\Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory {

        $payload = $request->all();
        WebhookReceived::dispatch($payload);
        $handler = LaravelPayments::resolveProviderWebhookHandler();

        if ($handler) {
            try {
                $handlerInstance = app()->make($handler);
                $handlerInstance->handle($payload);
            } catch (InvalidEventName $e) {
                WebhookFailed::dispatch($payload, $e);
                return response('Webhook received but event name was not found.');
            } catch (InvalidCustomPayload $e) {
                WebhookFailed::dispatch($payload, $e);
                return response('Webhook skipped due to invalid custom data.');
            } catch (HandleEventMethodNotImplemented $e) {
                WebhookFailed::dispatch($payload, $e);
                return response('Webhook skipped no handle method in handler.');
            // @codeCoverageIgnoreStart
            } catch (\Exception $e) {
                WebhookFailed::dispatch($payload, $e);
                return response('Webhook failed to be handled.');
            }
            // @codeCoverageIgnoreEnd

            WebhookHandled::dispatch($payload);
            return response('Webhook was handled.');
        }

        WebhookUnhandled::dispatch($payload);
        return response('Webhook received but no handler found.');
    }
}
