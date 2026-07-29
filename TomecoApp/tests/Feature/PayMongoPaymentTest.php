<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayMongoPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_can_create_checkout_for_own_violation(): void
    {
        config()->set('services.paymongo.secret_key', 'sk_test_example');
        Http::fake([
            'api.paymongo.com/v1/checkout_sessions' => Http::response([
                'data' => [
                    'id' => 'cs_test_123',
                    'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/test'],
                ],
            ], 200),
        ]);

        $driver = User::factory()->create(['role' => User::ROLE_DRIVER, 'plateNumber' => 'ABC 123']);
        $violation = Violation::create([
            'driver_name' => $driver->fullName,
            'plate_number' => 'ABC 123',
            'violation_type' => 'Illegal parking',
            'fine_amount' => 500.25,
        ]);

        $this->actingAs($driver)->post(route('payments.checkout', $violation))
            ->assertRedirect('https://checkout.paymongo.com/test');

        $this->assertDatabaseHas('payments', [
            'violation_id' => $violation->id,
            'amount' => 50025,
            'paymongo_checkout_session_id' => 'cs_test_123',
        ]);

        Http::assertSent(fn ($request) => $request['data']['attributes']['line_items'][0]['amount'] === 50025);
    }

    public function test_driver_cannot_pay_another_drivers_violation(): void
    {
        $driver = User::factory()->create(['role' => User::ROLE_DRIVER, 'plateNumber' => 'ABC 123']);
        $violation = Violation::create([
            'driver_name' => 'Other Driver',
            'plate_number' => 'XYZ 999',
            'violation_type' => 'Illegal parking',
            'fine_amount' => 500,
        ]);

        $this->actingAs($driver)->post(route('payments.checkout', $violation))->assertForbidden();
    }

    public function test_signed_paid_webhook_marks_payment_and_violation_paid(): void
    {
        config()->set('services.paymongo.webhook_secret', 'whsec_example');
        config()->set('services.paymongo.livemode', false);

        $violation = Violation::create([
            'driver_name' => 'Juan Dela Cruz',
            'plate_number' => 'ABC 123',
            'violation_type' => 'Illegal parking',
            'fine_amount' => 500,
        ]);
        $payment = Payment::create([
            'violation_id' => $violation->id,
            'reference' => 'TOM-TEST-001',
            'amount' => 50000,
            'paymongo_checkout_session_id' => 'cs_test_123',
        ]);
        $payload = json_encode(['data' => ['attributes' => [
            'type' => 'checkout_session.payment.paid',
            'data' => ['id' => 'cs_test_123', 'attributes' => ['payments' => [['id' => 'pay_test_123']]]],
        ]]]);
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_example');

        $this->call('POST', route('paymongo.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},te={$signature}",
        ], $payload)->assertOk();

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid', 'paymongo_payment_id' => 'pay_test_123']);
        $this->assertDatabaseHas('violations', ['id' => $violation->id, 'status' => 'paid']);
    }
}
