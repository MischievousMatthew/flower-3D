<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\User;
use App\Models\VendorApplication;
use App\Support\DeliveryArea;
use Tests\TestCase;

/**
 * Reading whether a free-text delivery address is inside a shop's delivery
 * areas, and the order flag that holds an outside-area order back.
 */
class DeliveryAreaTest extends TestCase
{
    private function shop(string|array|null $areas): VendorApplication
    {
        return new VendorApplication(['service_areas' => $areas]);
    }

    public function test_a_city_or_its_whole_province_counts_as_covered(): void
    {
        $shop = $this->shop('Cavite, Makati');

        $this->assertTrue(DeliveryArea::check($shop, 'Blk 5 Lot 2, Brgy. San Agustin, Trece Martires City, Cavite')['covered']);
        $this->assertTrue(DeliveryArea::check($shop, '12 Ayala Ave, Makati, Metro Manila')['covered']);
        // No accents needed
        $this->assertTrue(DeliveryArea::check($shop, 'Paliparan, Dasmarinas')['covered']);
    }

    public function test_places_outside_or_unknown_are_not_covered(): void
    {
        $shop = $this->shop('Cavite, Makati');

        $this->assertFalse(DeliveryArea::check($shop, '1 Taft Ave, Manila, Metro Manila')['covered']);
        $this->assertFalse(DeliveryArea::check($shop, 'Brgy Sto Nino, Calamba, Laguna')['covered']);
        $this->assertFalse(DeliveryArea::check($shop, 'Blk 2 Lot 3 Purok 4')['covered']);
    }

    public function test_longer_names_are_read_first(): void
    {
        // "Metro Manila" isn't also read as Manila, nor "Cavite City" as the province
        $this->assertSame(['Metro Manila', 'Makati'], DeliveryArea::placesIn('Makati, Metro Manila'));
        $this->assertSame(['Cavite City'], DeliveryArea::placesIn('Caridad, Cavite City'));
        $this->assertFalse(DeliveryArea::check($this->shop('Manila'), 'Pasig, Metro Manila')['covered']);
    }

    public function test_profile_city_is_used_when_the_address_names_no_place(): void
    {
        $customer = new User(['city' => 'Imus', 'region' => 'Cavite']);

        $this->assertTrue(DeliveryArea::check($this->shop('Imus'), 'Blk 2 Lot 3 Purok 4', $customer)['covered']);
        // The address wins when it names a place
        $this->assertFalse(DeliveryArea::check($this->shop('Imus'), '1 Taft Ave, Manila', $customer)['covered']);
    }

    public function test_a_shop_with_no_areas_delivers_anywhere_and_old_names_still_work(): void
    {
        $this->assertTrue(DeliveryArea::check($this->shop(null), 'Calamba, Laguna')['covered']);
        $this->assertFalse(DeliveryArea::check($this->shop(null), 'Calamba, Laguna')['restricted']);
        // Saved by the old registration form, or as a JSON list
        $this->assertTrue(DeliveryArea::check($this->shop('NCR (National Capital Region)'), 'Pasig, Metro Manila')['covered']);
        $this->assertTrue(DeliveryArea::check($this->shop('["Bacoor"]'), 'Molino, Bacoor')['covered']);
    }

    public function test_after_approval_wallet_orders_pay_in_full_and_cod_pays_half_online(): void
    {
        $base = ['outside_area' => true, 'status' => 'pending', 'payment_status' => 'unpaid', 'total_amount' => 1500];

        // Nothing is due before the shop approves
        $this->assertNull((new Order($base + ['payment_method' => 'ewallet']))->amountDueOnline());

        $approved = $base + ['area_approved_at' => now(), 'pay_by' => now()->addHours(Order::OUTSIDE_PAY_HOURS)];
        $wallet = new Order($approved + ['payment_method' => 'ewallet']);
        $this->assertSame(1500.0, $wallet->amountDueOnline());
        $this->assertTrue($wallet->awaitingOutsidePayment());
        $this->assertNull((new Order(['payment_status' => 'paid'] + $approved + ['payment_method' => 'ewallet']))->amountDueOnline());

        $cod = new Order($approved + ['payment_method' => 'cod', 'deposit_amount' => 750]);
        $this->assertSame(750.0, $cod->amountDueOnline());
        $this->assertSame(1500.0, $cod->cashOnDelivery());

        // Down payment made: nothing more online, the rest in cash
        $paid = new Order($approved + ['payment_method' => 'cod', 'deposit_amount' => 750, 'deposit_paid_at' => now()]);
        $this->assertNull($paid->amountDueOnline());
        $this->assertFalse($paid->awaitingOutsidePayment());
        $this->assertSame(750.0, $paid->cashOnDelivery());

        // Orders inside the areas are never asked to pay this way
        $this->assertNull((new Order(['outside_area' => false, 'area_approved_at' => now()] + $base + ['payment_method' => 'cod']))->amountDueOnline());
    }

    public function test_outside_area_orders_wait_until_approved(): void
    {
        $this->assertFalse((new Order(['outside_area' => false]))->awaitingAreaApproval());
        $this->assertTrue((new Order(['outside_area' => true]))->awaitingAreaApproval());
        $this->assertFalse((new Order(['outside_area' => true, 'area_approved_at' => now()]))->awaitingAreaApproval());
    }
}
