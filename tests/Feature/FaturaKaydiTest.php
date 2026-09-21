<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Mail\FaturaGonderimi;
use App\Models\Order;
use App\Models\User;
use App\Services\Faturalar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class FaturaKaydiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake('local');
        Storage::fake('public');
    }

    private function siparis(array $alanlar = []): Order
    {
        return Order::create(array_merge([
            'number' => 'ZEY-260921-0001',
            'customer_name' => 'Ayşe Yılmaz',
            'customer_email' => 'ayse@example.test',
            'customer_phone' => '5551112233',
            'shipping_address' => ['name' => 'Ayşe Yılmaz', 'line1' => 'Cumhuriyet Mah. 1', 'district' => 'Merkez', 'city' => 'Edirne'],
            'billing_address' => ['invoice_type' => 'corporate', 'company_name' => 'Örnek Tekstil A.Ş.', 'tax_office' => 'Edirne', 'tax_number' => '1234567890',
                'name' => 'Örnek Tekstil A.Ş.', 'line1' => 'Sanayi Cad. 5', 'city' => 'Edirne'],
            'subtotal' => 2890, 'shipping_total' => 0, 'discount_total' => 0, 'grand_total' => 2890,
            'status' => 'paid',
            'payment_status' => 'paid',
            'paid_at' => '2026-09-10 12:00',
        ], $alanlar));
    }

    public function test_panelden_fatura_girilir_pdf_gizli_diske_yazilir_ve_gonderilir(): void
    {
        $siparis = $this->siparis();

        Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(ListOrders::class)
            ->callTableAction('fatura', $siparis, [
                'numara' => 'ZEY2026000000042',
                'tarih' => '2026-09-11',
                'pdf' => UploadedFile::fake()->create('fatura.pdf', 40, 'application/pdf'),
                'gonder' => true,
            ])
            ->assertHasNoTableActionErrors();

        $siparis->refresh();
        $this->assertSame('ZEY2026000000042', $siparis->invoice_number);
        $this->assertSame('2026-09-11', $siparis->invoice_date->toDateString());
        Storage::disk('local')->assertExists($siparis->invoice_pdf);
        Storage::disk('public')->assertMissing($siparis->invoice_pdf);
        $this->assertNotNull($siparis->invoice_sent_at);

        Mail::assertSent(FaturaGonderimi::class, fn (FaturaGonderimi $m) => $m->hasTo('ayse@example.test')
            && count($m->attachments()) === 1
            && str_contains($m->envelope()->subject, 'ZEY-260921-0001'));
    }

    public function test_personel_de_fatura_girebilir(): void
    {
        $siparis = $this->siparis();

        Livewire::actingAs(User::factory()->create(['role' => 'staff']))->test(ListOrders::class)
            ->assertTableActionVisible('fatura', $siparis)
            ->assertActionHidden('muhasebe');
    }

    public function test_fatura_numarasi_tekrar_edemez(): void
    {
        $this->siparis(['number' => 'ZEY-A', 'invoice_number' => 'F-1']);
        $ikinci = $this->siparis(['number' => 'ZEY-B']);

        Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(ListOrders::class)
            ->callTableAction('fatura', $ikinci, [
                'numara' => 'F-1', 'tarih' => '2026-09-11',
                'pdf' => UploadedFile::fake()->create('f.pdf', 10, 'application/pdf'), 'gonder' => false,
            ])
            ->assertHasTableActionErrors(['numara']);
    }

    public function test_odenmemis_sipariste_fatura_eylemi_yok(): void
    {
        $siparis = $this->siparis(['payment_status' => 'pending', 'status' => 'pending']);

        Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(ListOrders::class)
            ->assertTableActionHidden('fatura', $siparis);
    }

    public function test_yeni_pdf_eskisinin_yerine_gecer(): void
    {
        $siparis = $this->siparis();
        Storage::disk('local')->put('faturalar/eski.pdf', 'x');
        $siparis->update(['invoice_number' => 'F-9', 'invoice_pdf' => 'faturalar/eski.pdf']);
        Storage::disk('local')->put('faturalar/yeni.pdf', 'y');

        app(Faturalar::class)->kaydet($siparis, 'F-9', '2026-09-12', 'faturalar/yeni.pdf', false);

        Storage::disk('local')->assertMissing('faturalar/eski.pdf');
        $this->assertSame('faturalar/yeni.pdf', $siparis->fresh()->invoice_pdf);
    }

    public function test_musteri_imzali_baglantiyla_indirir_imzasiz_indiremez(): void
    {
        Storage::disk('local')->put('faturalar/f.pdf', '%PDF-1.4 ornek');
        $siparis = $this->siparis(['invoice_number' => 'F-7', 'invoice_pdf' => 'faturalar/f.pdf']);

        $sayfa = $this->get(URL::signedRoute('order.show', ['order' => $siparis->number]))->assertOk();
        $sayfa->assertSee('Faturanız hazır (F-7)', false);

        $this->get(URL::signedRoute('order.invoice', ['order' => $siparis->number]))
            ->assertOk()
            ->assertDownload('Fatura-F-7.pdf');

        $this->get('/siparis/'.$siparis->number.'/fatura')->assertForbidden();
    }

    public function test_faturasiz_sipariste_indirme_404(): void
    {
        $siparis = $this->siparis();

        $this->get(URL::signedRoute('order.invoice', ['order' => $siparis->number]))->assertNotFound();
    }

    public function test_muhasebe_dokumu(): void
    {
        $this->siparis(['invoice_number' => 'F-100', 'invoice_date' => '2026-09-11']);
        $this->siparis(['number' => 'ZEY-ODENMEDI', 'payment_status' => 'pending', 'paid_at' => null]);
        $this->siparis(['number' => 'ZEY-EYLUL-DISI', 'paid_at' => '2026-08-31 23:00']);

        $csv = app(Faturalar::class)->muhasebeCsv('2026-09-01', '2026-09-30');

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('ZEY-260921-0001;"10.09.2026 12:00"', $csv);
        $this->assertStringContainsString('Kurumsal;"Örnek Tekstil A.Ş.";1234567890;Edirne', $csv);
        $this->assertStringContainsString('2890,00', $csv);
        $this->assertStringContainsString('F-100;11.09.2026', $csv);
        $this->assertStringNotContainsString('ZEY-ODENMEDI', $csv);
        $this->assertStringNotContainsString('ZEY-EYLUL-DISI', $csv);
    }

    public function test_faturasiz_suzgeci(): void
    {
        $kesilmis = $this->siparis(['number' => 'ZEY-A', 'invoice_number' => 'F-1']);
        $bekleyen = $this->siparis(['number' => 'ZEY-B']);

        Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(ListOrders::class)
            ->filterTable('faturasiz')
            ->assertCanSeeTableRecords([$bekleyen])
            ->assertCanNotSeeTableRecords([$kesilmis]);
    }
}
