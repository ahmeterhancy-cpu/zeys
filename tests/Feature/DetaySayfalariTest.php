<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Cart;
use App\Services\Checkout;
use App\Services\OrderPayments;
use App\Services\OrderShipping;
use App\Services\Returns;
use App\Services\VariantMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * GERİLEME: panel DETAY sayfaları.
 *
 * Önceki panel testleri yalnızca LİSTELERİ açıyordu. Sipariş ve iade
 * detay formları tarih alanında `$state->format()` çağırıyordu; Filament
 * durumu dizgi olarak doldurduğu için HER siparişin detay sayfası 500
 * veriyordu ve hiçbir test yakalamamıştı.
 */
class DetaySayfalariTest extends TestCase
{
    use RefreshDatabase;

    private User $yonetici;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->yonetici = User::factory()->create(['role' => 'admin']);

        $urun = Product::create(['name' => 'Saten Midi Elbise', 'is_active' => true]);
        app(VariantMatrix::class)->generate($urun, ['Beden' => ['kind' => 'text', 'values' => ['M']]], 2890.00);
        $urun->fresh()->variants->each->update(['stock' => 5]);

        app(Cart::class)->add($urun->fresh()->variants->first(), 1);

        $this->order = app(Checkout::class)->place(
            ['name' => 'Ayşe', 'email' => 'a@example.test', 'phone' => '555'],
            ['name' => 'Ayşe', 'phone' => '555', 'line1' => 'X', 'district' => 'Y', 'city' => 'Edirne'],
        );

        app(OrderPayments::class)->markPaid($this->order);
        app(OrderShipping::class)->markShipped($this->order->fresh(), 'Yurtiçi', '123');
        app(OrderShipping::class)->markDelivered($this->order->fresh());
    }

    public function test_siparis_detayi_acilir_ve_tarihleri_bicimli_gosterir(): void
    {
        $this->actingAs($this->yonetici)
            ->get('/admin/orders/'.$this->order->id.'/edit')
            ->assertOk()
            ->assertSee($this->order->number)
            ->assertSee(now()->format('d.m.Y'));
    }

    public function test_siparis_detayi_kaydedilebilir(): void
    {
        // Salt okunur sanal alanlar kayitta modele yazilmaya calisilmamali
        Livewire::actingAs($this->yonetici)
            ->test(EditOrder::class, ['record' => $this->order->id])
            ->fillForm(['admin_note' => 'Hediye paketi istendi.', 'tracking_number' => '999'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Hediye paketi istendi.', $this->order->fresh()->admin_note);
        $this->assertSame('999', $this->order->fresh()->tracking_number);
    }

    public function test_iade_detayi_acilir(): void
    {
        $talep = app(Returns::class)->open($this->order->fresh('items'), 'return', 'beden', [
            $this->order->items->first()->id => ['quantity' => 1],
        ]);

        app(Returns::class)->markReceived($talep);

        $this->actingAs($this->yonetici)
            ->get('/admin/return-requests/'.$talep->id.'/edit')
            ->assertOk()
            ->assertSee($talep->number);
    }
}
