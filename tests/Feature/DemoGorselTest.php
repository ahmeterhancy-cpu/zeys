<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductMedia;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoGorselTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_her_urune_kapak_ve_renk_fotografi_baglar(): void
    {
        Storage::fake('public');

        $this->seed(DemoSeeder::class);

        foreach (Product::all() as $urun) {
            $this->assertNotNull($urun->hero_image, $urun->name.' kapaksız');
            Storage::disk('public')->assertExists($urun->hero_image);

            // İki renk → iki renk fotoğrafı + kart için bir genel görsel
            $this->assertSame(2, ProductMedia::where('product_id', $urun->id)->whereNotNull('product_option_value_id')->count());
            $this->assertSame(1, ProductMedia::where('product_id', $urun->id)->whereNull('product_option_value_id')->count());
        }

        $this->assertSame(0, Category::whereNull('image')->count());
        $this->assertSame(0, Collection::whereNull('image')->count());
    }

    public function test_tekrar_calistirinca_gorsel_cogalmaz(): void
    {
        Storage::fake('public');

        $this->seed(DemoSeeder::class);
        $ilk = ProductMedia::count();

        $this->seed(DemoSeeder::class);

        $this->assertSame($ilk, ProductMedia::count());
    }

    public function test_magazanin_kendi_gorseline_dokunmaz(): void
    {
        Storage::fake('public');

        $this->seed(DemoSeeder::class);
        $urun = Product::first();
        ProductMedia::create(['product_id' => $urun->id, 'path' => 'urunler/galeri/gercek.jpg', 'position' => 99]);

        $this->seed(DemoSeeder::class);

        $this->assertDatabaseHas('product_media', ['path' => 'urunler/galeri/gercek.jpg']);
    }
}
