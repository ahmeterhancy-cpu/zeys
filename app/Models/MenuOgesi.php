<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Panelden düzenlenen menü bağlantıları (Vitrin → Menüler).
 *
 * Bir konumda hiç aktif öğe yoksa vitrin o konumun varsayılan
 * bağlantılarını gösterir (bkz. varsayilan()) — tablo boşken site
 * menüsüz kalmaz. "Kategoriler" açılır menüsü kategorilerden, "Yasal"
 * sütunu yasal metinlerden otomatik gelir; burada düzenlenmez.
 */
class MenuOgesi extends Model
{
    protected $table = 'menu_ogeleri';

    protected $guarded = [];

    protected $casts = [
        'yeni_sekme' => 'boolean',
        'aktif' => 'boolean',
    ];

    public const KONUMLAR = [
        'ana' => 'Ana menü (renkli bant + telefon menüsü)',
        'ust-serit' => 'Üst şerit (sağdaki küçük bağlantılar)',
        'alt-yardim' => 'Alt bilgi — "Yardım" sütunu',
    ];

    /** @return list<array{etiket: string, adres: string, yeni_sekme: bool}> */
    public static function varsayilan(string $konum): array
    {
        $b = fn (string $etiket, string $yol) => ['etiket' => $etiket, 'adres' => url($yol), 'yeni_sekme' => false];

        return match ($konum) {
            'ana' => [
                $b('Ana Sayfa', '/'),
                $b('Koleksiyonlar', '/koleksiyonlar'),
                $b('Yeni Gelenler', '/koleksiyonlar?sirala=yeni'),
                $b('Sipariş Sorgula', '/siparis-sorgula'),
                $b('İletişim', '/iletisim'),
            ],
            'ust-serit' => [
                $b('Sipariş Sorgula', '/siparis-sorgula'),
            ],
            'alt-yardim' => [
                $b('Sipariş Sorgula', '/siparis-sorgula'),
                $b('İade ve Değişim', '/sayfa/iade-degisim'),
                $b('İletişim', '/iletisim'),
            ],
            default => [],
        };
    }

    /**
     * Konumun vitrinde gösterilecek bağlantıları: paneldekiler, yoksa varsayılan.
     *
     * @return list<array{etiket: string, adres: string, yeni_sekme: bool}>
     */
    public static function baglantilar(string $konum, ?Collection $hepsi = null): array
    {
        $ogeler = ($hepsi ?? static::where('aktif', true)->orderBy('sira')->get())
            ->where('konum', $konum)
            ->where('aktif', true)
            ->sortBy('sira');

        if ($ogeler->isEmpty()) {
            return static::varsayilan($konum);
        }

        return $ogeler->map(fn (self $o) => [
            'etiket' => $o->etiket,
            'adres' => str_starts_with($o->baglanti, 'http') ? $o->baglanti : url($o->baglanti),
            'yeni_sekme' => $o->yeni_sekme,
        ])->values()->all();
    }

    /**
     * Panelde "hazır sayfa" seçici: sitedeki sayfalar → yol.
     *
     * @return array<string, array<string, string>>
     */
    public static function hazirSayfalar(): array
    {
        return [
            'Sayfalar' => [
                '/' => 'Ana Sayfa',
                '/koleksiyonlar' => 'Mağaza (tüm ürünler)',
                '/koleksiyonlar?sirala=yeni' => 'Yeni gelenler',
                '/koleksiyonlar?sirala=indirim' => 'İndirimdekiler',
                '/siparis-sorgula' => 'Sipariş Sorgula',
                '/iletisim' => 'İletişim',
                '/ara' => 'Arama',
                '/hesap' => 'Hesabım',
            ],
            'Kategoriler' => Category::where('is_active', true)->orderBy('position')->pluck('name', 'slug')
                ->mapWithKeys(fn ($ad, $slug) => ['/kategori/'.$slug => $ad])->all(),
            'Koleksiyonlar' => \App\Models\Collection::where('is_active', true)->orderBy('position')->pluck('name', 'slug')
                ->mapWithKeys(fn ($ad, $slug) => ['/koleksiyon/'.$slug => $ad])->all(),
            'Bilgi sayfaları' => [
                '/sayfa/iade-degisim' => 'İade ve Değişim',
                '/sayfa/on-bilgilendirme' => 'Ön Bilgilendirme Formu',
                '/sayfa/mesafeli-satis' => 'Mesafeli Satış Sözleşmesi',
                '/sayfa/kvkk' => 'KVKK Aydınlatma Metni',
                '/sayfa/cerez' => 'Çerez Politikası',
            ],
        ];
    }
}
