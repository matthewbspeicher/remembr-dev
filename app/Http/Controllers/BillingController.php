<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class BillingController extends Controller
{
    public function pricing(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Pricing', [
            'isPro' => $user?->isPro() ?? false,
        ]);
    }

    public function checkout(Request $request)
    {
        $user = $request->user();
        $priceId = config('stripe.pro_price_id');

        if (! $priceId) {
            return redirect()->route('pricing')
                ->with('error', 'Billing is not configured. Please contact support.');
        }

        return $user->newSubscription('default', $priceId)
            ->checkout([
                'success_url' => route('billing.success').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('pricing'),
            ]);
    }

    public function success(Request $request)
    {
        return redirect()->route('dashboard')
            ->with('message', 'Welcome to Pro! Your subscription is active.');
    }

    public function portal(Request $request)
    {
        return $request->user()->redirectToBillingPortal(route('dashboard'));
    }
}
