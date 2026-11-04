<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

abstract class HotelTestCase extends TestCase
{
    use RefreshDatabase;

    protected const PP = 'https://api-m.sandbox.paypal.com';

    protected RoomType $roomType;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'hotel.timezone' => 'UTC',
            'hotel.currency' => 'USD',
            'hotel.tax_percent' => 0,
            'hotel.deposit_percent' => 100,
            'hotel.hold_minutes' => 15,
            'hotel.cancellation_hours' => 48,
            'hotel.payment_methods' => ['paypal', 'pay_at_hotel'],
            'payment.paypal.client_id' => 'test-client',
            'payment.paypal.client_secret' => 'test-secret',
            'payment.paypal.webhook_id' => 'WH-TEST',
            'payment.paypal.base_url' => self::PP,
        ]);

        Mail::fake();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->roomType = RoomType::create([
            'name' => 'Deluxe', 'slug' => 'deluxe', 'base_price' => 100,
            'max_adults' => 2, 'max_children' => 1, 'max_occupancy' => 3,
        ]);
    }

    protected function addRooms(int $count, ?RoomType $type = null): void
    {
        $type ??= $this->roomType;
        $start = Room::count() + 1;
        for ($i = 0; $i < $count; $i++) {
            Room::create(['room_type_id' => $type->id, 'number' => (string) (100 + $start + $i)]);
        }
    }

    protected function date(int $daysFromToday): string
    {
        return now('UTC')->startOfDay()->addDays($daysFromToday)->toDateString();
    }

    protected function bookingPayload(array $overrides = []): array
    {
        return array_merge([
            'room_type_id' => $this->roomType->id,
            'check_in' => $this->date(10),
            'check_out' => $this->date(13),
            'adults' => 2,
            'children' => 0,
            'guest_name' => 'Ana Silva',
            'guest_email' => 'ana@example.com',
            'payment_method' => 'pay_at_hotel',
        ], $overrides);
    }

    protected function staff(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /**
     * Fake PayPal: token, order creation and (optionally) capture.
     *
     * @param string $captureStatus COMPLETED | DECLINED | PENDING
     */
    protected function fakePayPal(string $orderId = 'ORDER-1', string $captureStatus = 'COMPLETED', ?string $captureAmount = null, array $extra = []): void
    {
        Http::fake(array_merge([
            self::PP . '/v1/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 32400]),

            self::PP . '/v2/checkout/orders' => function ($request) use ($orderId) {
                return Http::response([
                    'id' => $orderId,
                    'status' => 'CREATED',
                    'links' => [['rel' => 'approve', 'href' => "https://www.sandbox.paypal.com/checkoutnow?token={$orderId}"]],
                    'echo' => $request->data(),
                ], 201);
            },

            self::PP . "/v2/checkout/orders/{$orderId}/capture" => function () use ($orderId, $captureStatus, $captureAmount) {
                if ($captureStatus === 'DECLINED') {
                    return Http::response(['name' => 'UNPROCESSABLE_ENTITY', 'details' => [['issue' => 'INSTRUMENT_DECLINED']]], 422);
                }

                return Http::response([
                    'id' => $orderId,
                    'status' => $captureStatus === 'COMPLETED' ? 'COMPLETED' : 'APPROVED',
                    'payer' => ['email_address' => 'buyer@example.com'],
                    'purchase_units' => [[
                        'payments' => ['captures' => [[
                            'id' => 'CAP-' . $orderId,
                            'status' => $captureStatus,
                            'amount' => ['currency_code' => 'USD', 'value' => $captureAmount ?? '300.00'],
                        ]]],
                    ]],
                ], 201);
            },

            self::PP . '/v2/payments/captures/*/refund' => Http::response([
                'id' => 'REF-1', 'status' => 'COMPLETED', 'amount' => ['value' => '300.00', 'currency_code' => 'USD'],
            ], 201),

            self::PP . '/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => 'SUCCESS']),
        ], $extra));
    }

    protected function paypalCalls(string $pathFragment): int
    {
        return Http::recorded(fn ($request) => str_contains($request->url(), $pathFragment))->count();
    }
}
