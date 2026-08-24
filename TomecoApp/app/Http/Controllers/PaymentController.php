<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\User;
use App\Models\Violation;
use App\Services\PayMongoClient;
use App\Services\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentController extends Controller
{
    public function checkout(Request $request, Violation $violation, PayMongoClient $payMongo, Settings $settings): RedirectResponse
    {
        $this->authorizeViolation($request, $violation);

        if (! $settings->get('payments.online_enabled', true)) {
            return to_route('dashboard.payments')->with('error', 'Online payments are currently disabled by an administrator.');
        }

        if ($violation->status === 'paid') {
            return to_route('dashboard.payments')->with('error', 'This violation has already been paid.');
        }

        $payment = Payment::query()
            ->where('violation_id', $violation->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if ($payment?->checkout_url) {
            return redirect()->away($payment->checkout_url);
        }

        $payment ??= Payment::create([
            'violation_id' => $violation->id,
            'user_id' => $request->user()->id,
            'reference' => 'TOM-'.now()->format('Ymd').'-'.strtoupper(Str::random(8)),
            'amount' => (int) round(((float) $violation->fine_amount) * 100),
            'currency' => 'PHP',
        ]);

        try {
            $session = $payMongo->createCheckoutSession($payment);
            $payment->update([
                'paymongo_checkout_session_id' => $session['id'],
                'checkout_url' => $session['attributes']['checkout_url'],
            ]);
        } catch (RuntimeException $exception) {
            return to_route('dashboard.payments')->with('error', $exception->getMessage());
        }

        return redirect()->away($payment->checkout_url);
    }

    public function success(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorizePayment($request, $payment);

        return to_route('dashboard.payments')->with(
            'success',
            $payment->status === 'paid'
                ? 'Payment confirmed. Thank you!'
                : 'Payment received and is being confirmed by PayMongo.',
        );
    }

    public function cancel(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorizePayment($request, $payment);

        return to_route('dashboard.payments')->with('error', 'Payment was cancelled. You can try again anytime.');
    }

    public function webhook(Request $request)
    {
        if (! $this->validSignature($request)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $event = $request->json()->all();
        if (data_get($event, 'data.attributes.type') !== 'checkout_session.payment.paid') {
            return response()->json(['received' => true]);
        }

        $session = data_get($event, 'data.attributes.data');
        $payment = Payment::where('paymongo_checkout_session_id', data_get($session, 'id'))->first();
        if (! $payment || $payment->status === 'paid') {
            return response()->json(['received' => true]);
        }

        DB::transaction(function () use ($payment, $session): void {
            $payment->update([
                'status' => 'paid',
                'paymongo_payment_id' => data_get($session, 'attributes.payments.0.id'),
                'paid_at' => now(),
            ]);
            $payment->violation()->update(['status' => 'paid']);
        });

        return response()->json(['received' => true]);
    }

    private function validSignature(Request $request): bool
    {
        $secret = (string) config('services.paymongo.webhook_secret');
        $header = (string) $request->header('Paymongo-Signature');
        if ($secret === '' || $header === '') {
            return false;
        }

        $parts = collect(explode(',', $header))->mapWithKeys(function (string $part): array {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            return [$key => $value];
        });
        $timestamp = $parts->get('t');
        $signature = $parts->get(config('services.paymongo.livemode') ? 'li' : 'te');

        return $timestamp && $signature
            && abs(now()->timestamp - (int) $timestamp) <= 300
            && hash_equals($signature, hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret));
    }

    private function authorizeViolation(Request $request, Violation $violation): void
    {
        abort_unless(
            in_array($request->user()->role, [User::ROLE_ADMIN, User::ROLE_OFFICER], true)
            || $violation->user_id === $request->user()->id,
            403,
        );
    }

    private function authorizePayment(Request $request, Payment $payment): void
    {
        abort_unless(
            in_array($request->user()->role, [User::ROLE_ADMIN, User::ROLE_OFFICER], true)
            || $payment->user_id === $request->user()->id,
            403,
        );
    }
}
