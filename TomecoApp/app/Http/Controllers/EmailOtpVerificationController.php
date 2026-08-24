<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\EmailVerificationOtp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class EmailOtpVerificationController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        return view('auth.verify-email');
    }

    public function verify(Request $request): RedirectResponse
    {
        $attributes = $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if ($user->email_verification_otp_expires_at === null || $user->email_verification_otp_expires_at->isPast()) {
            return back()->withErrors(['otp' => 'This verification code has expired. Request a new code.']);
        }

        if (! Hash::check($attributes['otp'], (string) $user->email_verification_otp)) {
            return back()->withErrors(['otp' => 'The verification code is incorrect.']);
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'email_verification_otp' => null,
            'email_verification_otp_expires_at' => null,
            'email_verification_otp_sent_at' => null,
        ])->save();

        return redirect()->intended(route('dashboard'))->with('status', 'Your email has been verified.');
    }

    public function resend(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        if ($user->email_verification_otp_sent_at?->addSeconds(60)->isFuture()) {
            return back()->withErrors(['otp' => 'Please wait before requesting another code.']);
        }

        $this->sendOtp($user);

        return back()->with('status', 'A new verification code has been sent.');
    }

    public static function sendOtp(User $user): void
    {
        $otp = (string) random_int(100000, 999999);

        $user->forceFill([
            'email_verification_otp' => Hash::make($otp),
            'email_verification_otp_expires_at' => now()->addMinutes(10),
            'email_verification_otp_sent_at' => now(),
        ])->save();

        $user->notify(new EmailVerificationOtp($otp));
    }
}
