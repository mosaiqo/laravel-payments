<?php

namespace Mosaiqo\LaravelPayments;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\IntlMoneyFormatter;
use Money\Money;
use Mosaiqo\LaravelPayments\ApiClients\ApiClient;
use Mosaiqo\LaravelPayments\Exceptions\MissingProvider;
use Mosaiqo\LaravelPayments\Http\Middleware\LemonSqueezyVerifyWebhookSignature;
use Mosaiqo\LaravelPayments\Http\Middleware\StripeVerifyWebhookSignature;
use Mosaiqo\LaravelPayments\Models\Customer;
use Mosaiqo\LaravelPayments\Models\Order;
use Mosaiqo\LaravelPayments\Models\Subscription;
use Mosaiqo\LaravelPayments\Models\Webhooks;
use Mosaiqo\LaravelPayments\WebhookHandlers\LemonSqueezyWebhookHandler;
use Mosaiqo\LaravelPayments\WebhookHandlers\StripeWebhookHandler;
use NumberFormatter;

class LaravelPayments
{
    const VERSION = '0.0.1';

    const API_URL = 'https://api.lemonsqueezy.com/v1';

    const PROVIDER_LEMON_SQUEEZY = 'lemon-squeezy';

    const PROVIDER_STRIPE = 'stripe';

    protected array $allowedProviders = [
        self::PROVIDER_LEMON_SQUEEZY,
        self::PROVIDER_STRIPE,
    ];

    /**
     * Indicates if missing providers should be allowed.
     */
    public static bool $allowMissingProviders = false;

    /**
     * Indicates if non-authenticated billables should be allowed.
     */
    public static bool $nonAuthenticatedBillablesAllowed = false;

    /**
     * Indicates if migrations will be run.
     */
    public static bool $runsMigrations = true;

    /**
     * Indicates if routes will be registered.
     */
    public static bool $registersRoutes = true;

    /**
     * Indicates if the package uses the default Laravel User model.
     */
    public static string $billableModel = 'App\\Models\\User';

    public static $resolveBillableForUserFunction = null;

    /**
     * @var string The customer model that will be used to store the customer information.
     */
    public static string $customerModel = Customer::class;

    /**
     * Indicates if the package uses the default Laravel User model.
     */
    public static string $orderModel = Order::class;

    /**
     * Indicates if the package uses the default Laravel User model.
     */
    public static string $subscriptionModel = Subscription::class;

    /**
     * The webhook handler that will be used to handle provider webhooks.
     * @var string
     */
    public static string $webhooksModel = Webhooks::class;

    /**
     * The webhook handler that will be used to handle provider webhooks.
     */
    public static string $lemonSqueezyWebhookHandler = LemonSqueezyWebhookHandler::class;

    /**
     * The signature middleware that will be used to verify the webhook signature.
     *
     * @var string The signature middleware that will be used to verify the webhook signature.
     */
    public static string $lemonSqueezyVerifyWebhookSignature = LemonSqueezyVerifyWebhookSignature::class;

    /**
     * The webhook handler that will be used to handle provider webhooks.
     * @var string
     */
    public static string $stripeWebhookHandler = StripeWebhookHandler::class;

    /**
     * The signature middleware that will be used to verify the webhook signature.
     * @var string
     */
    public static string $stripeVerifyWebhookSignature = StripeVerifyWebhookSignature::class;


    public static bool $asyncWebhooks = false;

    public static bool $storeWebhooks = false;

    public static function storeWebhooks(bool $store = true): void
    {
        static::$storeWebhooks = $store;
    }

    public static function asyncWebhooks(bool $async = true): void
    {
        static::storeWebhooks();
        static::$asyncWebhooks = $async;
    }

    public static function syncWebhooks(): void
    {
        static::asyncWebhooks(false);
    }

    public static function useWebhooksModel(string $webhooksModel): void
    {
        static::$webhooksModel = $webhooksModel;
    }

    /**
     * Indicates what customer model should be used.
     */
    public static function useBillableModel(string $billableModel, $resolveBillableForUserFunction = null): void
    {
        static::$billableModel = $billableModel;
        static::$resolveBillableForUserFunction = $resolveBillableForUserFunction;
    }

    /**
     * Indicates what customer model should be used.
     */
    public static function useCustomerModel(string $customerModel): void
    {
        static::$customerModel = $customerModel;
    }

    /**
     * Indicates what order model should be used.
     */
    public static function useOrderModel(string $orderModel): void
    {
        static::$orderModel = $orderModel;
    }

    /**
     * Indicates what subscription model should be used.
     */
    public static function useSubscriptionModel(string $subscriptionModel): void
    {
        static::$subscriptionModel = $subscriptionModel;
    }

    /**
     * Indicates what webhook handler should be used for Lemon Squeezy.
     */
    public static function useLemonSqueezyWebhookHandler(string $lemonSqueezyWebhookHandler): void
    {
        static::$lemonSqueezyWebhookHandler = $lemonSqueezyWebhookHandler;
    }

    /**
     * @param string $lemonSqueezyVerifyWebhookSignature
     *
     * @return void
     */
    public static function useLemonSqueezyVerifyWebhookSignature(string $lemonSqueezyVerifyWebhookSignature): void
    {
        static::$lemonSqueezyVerifyWebhookSignature = $lemonSqueezyVerifyWebhookSignature;
    }


    /**
     * Indicates what webhook handler should be used for Stripe.
     */
    public static function useStripeWebhookHandler(string $stripeWebhookHandler): void
    {
        static::$stripeWebhookHandler = $stripeWebhookHandler;
    }

    /**
     * @param string $stripeVerifyWebhookSignature
     *
     * @return void
     */
    public static function useStripeVerifyWebhookSignature(string $stripeVerifyWebhookSignature): void
    {
        static::$stripeVerifyWebhookSignature = $stripeVerifyWebhookSignature;
    }

    /**
     * @param true $allow
     *
     * @return void
     */
    public static function allowNonAuthenticatedBillables(bool $allow = true): void
    {
        static::$nonAuthenticatedBillablesAllowed = $allow;
    }

    public static function areNonAuthenticatedBillablesAllowed(): bool
    {
        return static::$nonAuthenticatedBillablesAllowed;
    }

    /**
     * Get the correct API client.
     */
    public static function api()
    {
        return ApiClient::make();
    }

    /**
     * Resolves the provider to use.
     */
    public static function getProvider(): ?string
    {
        return config('payments.provider');
    }

    /**
     * Resolves all the providers.
     */
    public static function getProviders(): array
    {
        return config('payments.providers');
    }

    /**
     * Resolves the webhooks model to use.
     */
    public static function resolveWebhooksModel(): string
    {
        return static::$webhooksModel;
    }

    /**
     * Resolves the customer model to use.
     */
    public static function resolveCustomerModel(): string
    {
        return static::$customerModel;
    }

    /**
     * Resolves the customer model to use.
     */
    public static function resolveBillableModel(): string
    {
        return static::$billableModel;
    }


    /**
     * Resolves the customer model to use.
     */
    public static function resolveBillableForUser($user)
    {
        if (static::$resolveBillableForUserFunction && is_callable(static::$resolveBillableForUserFunction)) {
            return call_user_func_array(static::$resolveBillableForUserFunction, [$user]);
        }

        return $user;
    }

    /**
     * Resolves the order model to use.
     */
    public static function resolveOrderModel(): string
    {
        return static::$orderModel;
    }

    /**
     * Resolves the subscription model to use.
     */
    public static function resolveSubscriptionModel(): string
    {
        return static::$subscriptionModel;
    }

    /**
     * Resolves the webhook handler to use for the configured provider.
     */
    public static function resolveProviderWebhookHandler(): ?string
    {
        $handler = Str::camel(static::getProvider()) . 'WebhookHandler';
        if (!property_exists(static::class, $handler)) {
            return null;
        }

        return static::$$handler;
    }

    /**
     * Resolves the signature middleware to use for the configured provider.
     */
    public static function resolveProviderSignatureMiddleware(): ?string
    {
        $middleware = Str::camel(static::getProvider()) . 'VerifyWebhookSignature';
        if (!property_exists(static::class, $middleware)) {
            return null;
        }

        return static::$$middleware;
    }

    /**
     * Determine if the provider should inject the signature middleware.
     */
    public static function shouldInjectSignatureMiddleware(): bool
    {
        $provider = static::getProvider();
        $providers = static::getProviders();

        if (!array_key_exists($provider, $providers)) {
            return false;
        }

        if (!isset($providers[$provider]['signing_secret'])) {
            return false;
        }

        return true;
    }

    /**
     * Determine if the route should return a 404 response.
     */
    public static function shouldReturnNotFound(): bool
    {
        try {
            static::checkProviderIsConfigured();
        } catch (MissingProvider $e) {
            return true;
        }

        return false;
    }


    /**
     * @throws \Mosaiqo\LaravelPayments\Exceptions\MissingProvider
     */
    public static function resolveProviderConfig(): ?array
    {
        $provider = static::getProvider();
        $providers = static::getProviders();

        if (!array_key_exists($provider, $providers)) {
            throw MissingProvider::notConfigured();
        }

        return $providers[$provider];
    }


    public static function config($key = null)
    {
        return config("payments" . ($key ? '.' . $key : ''));
    }


    public static function providerConfig($key = null)
    {
        return config("payments.providers." . static::getProvider() . ($key ? '.' . $key : ''));
    }


    public static function getWebHookPath()
    {
        return config('payments.path') . '/' . config('payments.webhook_path');
    }


    /**
     * @throws \Mosaiqo\LaravelPayments\Exceptions\MissingProvider
     */
    public static function checkProviderIsConfigured(): void
    {
        $provider = static::getProvider();

        if (!$provider) {
            throw MissingProvider::notConfigured();
        }

        if (!static::$allowMissingProviders && !in_array($provider, (new self)->allowedProviders)) {
            throw MissingProvider::notSupported($provider, static::allowedProviders());
        }
    }

    /**
     * @return array
     */
    public static function allowedProviders(): array
    {
        $instance = new self;
        return $instance->allowedProviders;
    }

    public static function queueWebhook(Request $request)
    {
        $model = static::resolveWebhooksModel();

        $model::create([
            'body' => $request->all(),
            'headers' => $request->header(),
            'provider' => static::getProvider(),
        ]);
    }

    /**
     * @param int    $amount
     * @param string $currency
     *
     * @return string
     */
    public static function formatAmount(int $amount, string $currency, ?string $locale = null, array $options = []): string
    {
        $money = new Money($amount, new Currency(strtoupper($currency)));

        $locale = $locale ?? static::resolveProviderConfig()['currency_locale'];

        $numberFormatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);

        if (isset($options['min_fraction_digits'])) {
            $numberFormatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $options['min_fraction_digits']);
        }

        $moneyFormatter = new IntlMoneyFormatter($numberFormatter, new ISOCurrencies());

        return $moneyFormatter->format($money);
    }
}
