<?php

namespace Mosaiqo\LaravelPayments\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Mosaiqo\LaravelPayments\LaravelPayments;
use Mosaiqo\LaravelPayments\PaymentsService;

class PaymentsCheckoutController extends Controller
{
    public function __construct()
    {
        $this->middleware('web');
    }

    public function __invoke(Request $request, $product, $variant = null)
    {
        $billable = LaravelPayments::resolveBillableForUser($request->user());
        $discountCode = $request->input('discount');

        $checkout = PaymentsService::checkout($variant, $discountCode, $billable);

        return redirect($checkout->url);
    }
}
