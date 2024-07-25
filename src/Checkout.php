<?php

namespace Mosaiqo\LaravelPayments;

use DateTimeInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Testing\Constraints\ArraySubset;
use Mosaiqo\LaravelPayments\ApiClients\LemonSqueezyApiClient;
use Mosaiqo\LaravelPayments\Exceptions\ReservedCustomKeys;
use Pest\Expectation;
use PHPUnit\Framework\Assert as PHPUnit;

class Checkout
{

    private bool $embed = false;

    private bool $media = true;

    private bool $logo = true;

    private bool $desc = true;

    private bool $discount = true;

    private bool $dark = false;

    private bool $subscriptionPreview = true;

    private array $checkoutData = [];

    private ?string $buttonColor;

    private array $custom = [];

    private ?string $productName = null;

    private ?string $description = null;

    private ?string $thankYouNote = null;

    private ?string $redirectUrl;

    private ?DateTimeInterface $expiresAt;

    private ?int $customPrice = null;

    public function __construct(private readonly string $store, private readonly string $variant) {}


    public static function make(string $store, string $variant): static
    {
        return new static($store, $variant);
    }


    public function withoutLogo(): self
    {
        $this->logo = false;

        return $this;
    }

    public function withoutMedia(): self
    {
        $this->media = false;

        return $this;
    }

    public function withoutDescription(): self
    {
        $this->desc = false;

        return $this;
    }

    public function withoutDiscountField(): self
    {
        $this->discount = false;

        return $this;
    }

    public function dark(): self
    {
        $this->dark = true;

        return $this;
    }

    public function withoutSubscriptionPreview(): self
    {
        $this->subscriptionPreview = false;

        return $this;
    }

    public function withButtonColor(string $color): self
    {
        $this->buttonColor = $color;

        return $this;
    }

    public function withName(string $name): self
    {
        $this->checkoutData['name'] = $name;

        return $this;
    }

    public function withEmail(string $email): self
    {
        $this->checkoutData['email'] = $email;

        return $this;
    }

    public function withBillingAddress(string $country, ?string $zip = null): self
    {
        $this->checkoutData['billing_address'] = array_filter([
            'country' => $country,
            'zip' => $zip,
        ]);

        return $this;
    }

    public function withTaxNumber(string $taxNumber): self
    {
        $this->checkoutData['tax_number'] = $taxNumber;

        return $this;
    }

    public function withDiscountCode(string $discountCode): self
    {
        $this->checkoutData['discount_code'] = $discountCode;

        return $this;
    }

    /**
     * @throws \Mosaiqo\LaravelPayments\Exceptions\ReservedCustomKeys
     */
    public function withCustomData(array $custom): static
    {
        if (
            (array_key_exists('billable_id', $custom) && isset($this->custom['billable_id'])) ||
            (array_key_exists('billable_type', $custom) && isset($this->custom['billable_type'])) ||
            (array_key_exists('subscription_type', $custom) && isset($this->custom['subscription_type']))
        ) {
            throw ReservedCustomKeys::overwriteAttempt();
        }

        $this->custom = collect(array_replace_recursive($this->custom, $custom))
            ->map(fn (mixed $value) => is_string($value) ? trim($value) : $value)
            ->filter(fn (mixed $value) => ! is_null($value))
            ->toArray();

        return $this;
    }

    public function withProductName(string $productName): self
    {
        $this->productName = $productName;

        return $this;
    }

    public function withDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function withThankYouNote(string $thankYouNote): self
    {
        $this->thankYouNote = $thankYouNote;

        return $this;
    }

    public function redirectTo(string $url): self
    {
        $this->redirectUrl = $url;

        return $this;
    }

    public function expiresAt(DateTimeInterface $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function withCustomPrice(?int $customPrice): self
    {
        $this->customPrice = $customPrice;

        return $this;
    }

    private function preparePayload(): array
    {
        return [
            'data' => [
                'type' => 'checkouts',
                'attributes' => [
                    'custom_price' => $this->customPrice,
                    'checkout_data' => array_merge(
                        array_filter($this->checkoutData, fn (mixed $value) => $value !== ''),
                        ['custom' => $this->custom]
                    ),
                    'checkout_options' => array_filter([
                        'embed' => $this->embed,
                        'logo' => $this->logo,
                        'media' => $this->media,
                        'desc' => $this->desc,
                        'discount' => $this->discount,
                        'dark' => $this->dark,
                        'subscription_preview' => $this->subscriptionPreview,
                        'button_color' => $this->buttonColor ?? null,
                    ], function (mixed $value) {
                        return ! is_null($value);
                    }),
                    'product_options' => array_filter([
                        'name' => $this->productName,
                        'description' => $this->description,
                        'receipt_thank_you_note' => $this->thankYouNote,
                        'redirect_url' => $this->redirectUrl ?? config('lemon-squeezy.redirect_url'),
                    ]),
                    'expires_at' => isset($this->expiresAt) ? $this->expiresAt->format(DateTimeInterface::ATOM) : null,
                ],
                'relationships' => [
                    'store' => [
                        'data' => [
                            'type' => 'stores',
                            'id' => $this->store,
                        ],
                    ],
                    'variant' => [
                        'data' => [
                            'type' => 'variants',
                            'id' => $this->variant,
                        ],
                    ],
                ],
            ],
        ];
    }


    /**
     * @throws \Mosaiqo\LaravelPayments\Exceptions\ApiError
     */
    public function url(): ?string
    {
        $response = LaravelPayments::api()->createCheckout($this->preparePayload());

        return $response['data']['attributes']['url'];
    }

    public function redirect(): RedirectResponse
    {
        return Redirect::to($this->url(), 303);
    }
}
