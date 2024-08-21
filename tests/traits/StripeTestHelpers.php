<?php

namespace Tests\Traits;

use Stripe\ApiRequestor;
use Stripe\HttpClient\CurlClient;
use Stripe\StripeClient;
use Tests\Fixtures\User;

trait StripeTestHelpers
{
    public static ?string $productId;
    public static ?string $priceId;
    public static ?string $otherPriceId;
    public static ?string $premiumPriceId;
    public static ?string $couponId;
    public static ?string $taxRateId;

    public static function stripe($options = []): StripeClient
    {
        $curl = new CurlClient([CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1]);
        $curl->setEnableHttp2(false);
        ApiRequestor::setHttpClient($curl);

        return new StripeClient([
            'api_key' => getenv('STRIPE_API_SECRET'),
        ], $options);
    }

    public function createCustomer($description, array $options = [])
    {
        return User::create(array_merge([
            'email' => "{$description}@laravel-payments-test.com",
            'name' => 'Boudy De Geer',
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        ], $options));
    }
}
