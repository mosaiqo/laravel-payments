<?php

namespace Mosaiqo\LaravelPayments;

use Illuminate\Support\Str;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\IntlMoneyFormatter;
use Money\Money;
use Mosaiqo\LaravelPayments\ApiClients\ApiClient;
use Mosaiqo\LaravelPayments\Exceptions\MissingProvider;
use Mosaiqo\LaravelPayments\Http\Middleware\LemonSqueezyVerifyWebhookSignature;
use Mosaiqo\LaravelPayments\Models\Customer;
use Mosaiqo\LaravelPayments\Models\Order;
use Mosaiqo\LaravelPayments\Models\Subscription;
use Mosaiqo\LaravelPayments\WebhookHandlers\LemonSqueezyWebhookHandler;
use NumberFormatter;

class LaravelPayments
{
    const VERSION = '0.0.1';

    const API_URL = 'https://api.lemonsqueezy.com/v1';

    const PROVIDER_LEMON_SQUEEZY = 'lemon-squeezy';

    protected array $allowedProviders = [
        self::PROVIDER_LEMON_SQUEEZY,
    ];

    /**
     * Indicates if missing providers should be allowed.
     */
    public static bool $allowMissingProviders = false;

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
     */
    public static string $lemonSqueezyWebhookHandler = LemonSqueezyWebhookHandler::class;

    public static string $lemonSqueezyVerifyWebhookSignature = LemonSqueezyVerifyWebhookSignature::class;

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
     * Resolves the customer model to use.
     */
    public static function resolveCustomerModel(): string
    {
        return static::$customerModel;
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
