<?php

namespace App\Services;

use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

/**
 * Varyant (SKU) bazında fiyat/stok tablosu — dışa ve içe aktarım.
 *
 * Mağaza stok sayımını Excel'de yapıyor. Dosya Türkçe Excel'in ürettiği
 * biçime göre kuruldu: `;` ayraç, ondalık virgül, başta UTF-8 BOM (BOM
 * olmadan Excel "ş/ğ/İ"yi bozuk açıyor).
 *
 * İçe aktarım YENİ VARYANT OLUŞTURMAZ; yalnız var olan SKU'ların fiyat,
 * eski fiyat, stok, barkod ve aktiflik değerlerini günceller. Yeni
 * beden/renk kombinasyonu panelden "Kombinasyon üret" ile açılır — CSV'den
 * açılsaydı seçenek eksenleri (Beden × Renk) tutarsız kalırdı.
 *
 * Hepsi ya da hiçbiri: tek satırda hata varsa HİÇBİR satır yazılmaz.
 * Yarım uygulanan bir sayım dosyası, hangi satırın işlendiğini bilmeden
 * düzeltmeye çalışan mağazayı çift sayıma götürür.
 */
class UrunCsv
{
    public const BASLIKLAR = ['sku', 'urun', 'varyant', 'barkod', 'fiyat', 'eski_fiyat', 'stok', 'rezerve', 'aktif'];

    /** Yalnız bu sütunlar içe aktarımda okunur; kalanlar bilgi amaçlı. */
    private const YAZILABILIR = ['barkod', 'fiyat', 'eski_fiyat', 'stok', 'aktif'];

    public function disaAktar(): string
    {
        $akis = fopen('php://temp', 'r+');
        fwrite($akis, "\xEF\xBB\xBF");
        fputcsv($akis, self::BASLIKLAR, ';', '"', '');

        ProductVariant::query()
            ->with(['product:id,name', 'optionValues.option'])
            ->orderBy('product_id')
            ->orderBy('id')
            ->each(function (ProductVariant $v) use ($akis) {
                fputcsv($akis, [
                    $v->sku,
                    $v->product?->name,
                    $v->label,
                    $v->barcode,
                    $this->para($v->price),
                    $v->compare_at_price === null ? '' : $this->para($v->compare_at_price),
                    $v->stock,
                    $v->reserved,
                    $v->is_active ? 'evet' : 'hayır',
                ], ';', '"', '');
            });

        rewind($akis);
        $icerik = stream_get_contents($akis);
        fclose($akis);

        return $icerik;
    }

    /**
     * @return array{guncellenen: int, degismeyen: int, hatalar: list<string>}
     */
    public function iceAktar(string $icerik): array
    {
        $icerik = preg_replace('/^\xEF\xBB\xBF/', '', $icerik);
        $satirlar = preg_split('/\r\n|\r|\n/', trim($icerik));

        if ($satirlar === [] || trim($satirlar[0]) === '') {
            return ['guncellenen' => 0, 'degismeyen' => 0, 'hatalar' => ['Dosya boş.']];
        }

        $ayrac = $this->ayracBul($satirlar[0]);
        $baslik = array_map(
            fn ($b) => mb_strtolower(trim((string) $b)),
            str_getcsv($satirlar[0], $ayrac, '"', ''),
        );

        if (! in_array('sku', $baslik, true)) {
            return ['guncellenen' => 0, 'degismeyen' => 0, 'hatalar' => ['Başlık satırında "sku" sütunu yok.']];
        }

        $hatalar = [];
        $degisiklikler = [];
        $gorulen = [];

        foreach (array_slice($satirlar, 1) as $i => $satir) {
            $no = $i + 2; // Excel satır numarası (başlık 1. satır)

            if (trim($satir) === '' || trim($satir, $ayrac.' ') === '') {
                continue;
            }

            $hucreler = str_getcsv($satir, $ayrac, '"', '');
            $kayit = [];
            foreach ($baslik as $j => $ad) {
                $kayit[$ad] = trim((string) ($hucreler[$j] ?? ''));
            }

            $sku = $kayit['sku'];

            if ($sku === '') {
                $hatalar[] = "Satır {$no}: SKU boş.";

                continue;
            }

            if (isset($gorulen[$sku])) {
                $hatalar[] = "Satır {$no}: {$sku} dosyada ikinci kez geçiyor (ilk: satır {$gorulen[$sku]}).";

                continue;
            }
            $gorulen[$sku] = $no;

            $varyant = ProductVariant::where('sku', $sku)->first();

            if (! $varyant) {
                $hatalar[] = "Satır {$no}: {$sku} bulunamadı. Yeni varyant CSV'den açılmaz; panelden ekleyin.";

                continue;
            }

            $yeni = [];

            foreach (self::YAZILABILIR as $alan) {
                if (! array_key_exists($alan, $kayit) || $kayit[$alan] === '') {
                    continue; // Boş hücre = değiştirme
                }

                $deger = $kayit[$alan];

                switch ($alan) {
                    case 'fiyat':
                        $sayi = $this->sayi($deger);
                        if ($sayi === null || $sayi <= 0) {
                            $hatalar[] = "Satır {$no}: {$sku} fiyatı geçersiz ({$deger}).";
                            break;
                        }
                        $yeni['price'] = $sayi;
                        break;

                    case 'eski_fiyat':
                        // "-" ya da "0" eski fiyatı kaldırır (indirim bitti)
                        if ($deger === '-' || $this->sayi($deger) === 0.0) {
                            $yeni['compare_at_price'] = null;
                            break;
                        }
                        $sayi = $this->sayi($deger);
                        if ($sayi === null || $sayi < 0) {
                            $hatalar[] = "Satır {$no}: {$sku} eski fiyatı geçersiz ({$deger}).";
                            break;
                        }
                        $yeni['compare_at_price'] = $sayi;
                        break;

                    case 'stok':
                        if (! preg_match('/^\d+$/', $deger)) {
                            $hatalar[] = "Satır {$no}: {$sku} stoğu tam sayı olmalı ({$deger}).";
                            break;
                        }
                        $stok = (int) $deger;
                        /*
                         * Stok, ödemesi beklenen siparişlerin tuttuğu adedin
                         * altına indirilemez: satılabilir stok eksiye düşer ve
                         * bekleyen ödeme gelince olmayan ürün satılmış olur.
                         */
                        if ($stok < $varyant->reserved) {
                            $hatalar[] = "Satır {$no}: {$sku} stoğu {$stok} yapılamaz — {$varyant->reserved} adet ödemesi beklenen siparişte ayrılmış.";
                            break;
                        }
                        $yeni['stock'] = $stok;
                        break;

                    case 'aktif':
                        $bool = $this->mantiksal($deger);
                        if ($bool === null) {
                            $hatalar[] = "Satır {$no}: {$sku} aktif değeri anlaşılamadı ({$deger}); evet/hayır yazın.";
                            break;
                        }
                        $yeni['is_active'] = $bool;
                        break;

                    case 'barkod':
                        $yeni['barcode'] = $deger;
                        break;
                }
            }

            $fiyat = $yeni['price'] ?? (float) $varyant->price;
            $eski = array_key_exists('compare_at_price', $yeni) ? $yeni['compare_at_price'] : $varyant->compare_at_price;

            $fiyatDegisti = isset($yeni['price']) || array_key_exists('compare_at_price', $yeni);

            if ($fiyatDegisti && $eski !== null && (float) $eski > 0 && (float) $eski <= $fiyat) {
                $hatalar[] = "Satır {$no}: {$sku} eski fiyat ({$this->para($eski)}) satış fiyatından ({$this->para($fiyat)}) büyük olmalı.";
            }

            $degisiklikler[] = [$varyant, $yeni];
        }

        if ($hatalar !== []) {
            return ['guncellenen' => 0, 'degismeyen' => 0, 'hatalar' => $hatalar];
        }

        $guncellenen = 0;
        $degismeyen = 0;

        DB::transaction(function () use ($degisiklikler, &$guncellenen, &$degismeyen) {
            foreach ($degisiklikler as [$varyant, $yeni]) {
                $varyant->fill($yeni);

                if (! $varyant->isDirty()) {
                    $degismeyen++;

                    continue;
                }

                // Gözlemci ürün önbelleğini tazeler ve "stoğa girdi" postalarını atar
                $varyant->save();
                $guncellenen++;
            }
        });

        return ['guncellenen' => $guncellenen, 'degismeyen' => $degismeyen, 'hatalar' => []];
    }

    private function ayracBul(string $baslik): string
    {
        $sayilar = [';' => substr_count($baslik, ';'), ',' => substr_count($baslik, ','), "\t" => substr_count($baslik, "\t")];
        arsort($sayilar);

        return array_key_first($sayilar);
    }

    /**
     * "1.450,00" / "1450,5" / "1450.50" / "1.450" → sayı.
     * Hem nokta hem virgül varsa sondaki ondalık ayraçtır. Yalnız nokta
     * varsa ve ardından tam üç hane geliyorsa ("1.450") binlik sayılır —
     * Türkçe Excel fiyatı böyle gösterir.
     */
    public function sayi(string $deger): ?float
    {
        $d = str_replace([' ', 'TL', '₺', "\u{00A0}"], '', $deger);

        if ($d === '' || ! preg_match('/^-?[\d.,]+$/', $d)) {
            return null;
        }

        $sonNokta = strrpos($d, '.');
        $sonVirgul = strrpos($d, ',');

        if ($sonNokta !== false && $sonVirgul !== false) {
            $ondalik = $sonNokta > $sonVirgul ? '.' : ',';
            $binlik = $ondalik === '.' ? ',' : '.';
            $d = str_replace([$binlik, $ondalik], ['', '.'], $d);
        } elseif ($sonVirgul !== false) {
            if (substr_count($d, ',') > 1) {
                return null;
            }
            $d = str_replace(',', '.', $d);
        } elseif ($sonNokta !== false && preg_match('/^-?\d{1,3}(\.\d{3})+$/', $d)) {
            $d = str_replace('.', '', $d);
        }

        return is_numeric($d) ? round((float) $d, 2) : null;
    }

    private function mantiksal(string $deger): ?bool
    {
        return match (mb_strtolower($deger)) {
            'evet', 'e', '1', 'true', 'aktif', 'açık', 'acik' => true,
            'hayır', 'hayir', 'h', '0', 'false', 'pasif', 'kapalı', 'kapali' => false,
            default => null,
        };
    }

    private function para(mixed $tutar): string
    {
        return number_format((float) $tutar, 2, ',', '');
    }
}
