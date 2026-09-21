@extends('layout.vitrin')

@section('baslik', 'Sipariş ' . $order->number . ' — ' . config('shop.ad'))

@section('icerik')
@php
    // Değişim için seçilebilecek varyantlar kalem bazında hazırlanıyor;
    // Blade içinde sorgu döndürmemek için burada toplanıyor.
    $degisimSecenekleri = [];

    foreach ($order->items as $kalem) {
        $urun = $kalem->variant?->product;

        $degisimSecenekleri[$kalem->id] = $urun
            ? $urun->variants()->where('is_active', true)->with('optionValues.option')->get()
            : collect();
    }
@endphp

@include('vitrin.parca.sayfa-basi', ['baslik' => 'Sipariş ' . $order->number, 'konum' => ['Sipariş Sorgula' => route('order.lookup.form'), $order->number => null]])

<div class="kap kap-dar siparis-sayfa">

    <div class="siparis-basi">
        <div>
            <h2>{{ $order->number }}</h2>
            <p class="siparis-tarih">{{ $order->created_at->format('d.m.Y H:i') }}</p>
        </div>
        <span class="siparis-durum siparis-durum-{{ $order->status }}">{{ $order->status_label }}</span>
    </div>

    @if ($order->invoice_pdf)
        <p class="fatura-bag">
            @include('vitrin.parca.ikon', ['ad' => 'eposta'])
            Faturanız hazır ({{ $order->invoice_number }}) —
            <a href="{{ \Illuminate\Support\Facades\URL::signedRoute('order.invoice', ['order' => $order->number]) }}">PDF olarak indirin</a>
        </p>
    @endif

    @if (session('bilgi'))
        <p class="uyari">{{ session('bilgi') }}</p>
    @endif
    @if (session('hata'))
        <p class="uyari uyari-hata">{{ session('hata') }}</p>
    @endif

    @if ($order->tracking_number)
        <div class="kargo-kutu">
            <span class="etiket">Kargo</span>
            <p>
                {{ $order->shipping_carrier }} ·
                <strong>{{ $order->tracking_number }}</strong>
            </p>
            @if ($order->shipped_at)
                <p class="kargo-tarih">{{ $order->shipped_at->format('d.m.Y') }} tarihinde kargoya verildi.</p>
            @endif
        </div>
    @endif

    {{-- Kalemler --}}
    <section class="bolum">
        <div class="bolum-basi">
            <h2>Ürünler</h2>
        </div>

        @foreach ($order->items as $kalem)
            <div class="siparis-kalem">
                <div class="sepet-gorsel {{ $kalem->image ? '' : 'urun-gorsel-yok' }}">
                    @if ($kalem->image)
                        <img src="{{ asset('storage/' . $kalem->image) }}" alt="{{ $kalem->name }}">
                    @else
                        <span class="urun-harf" aria-hidden="true">Z</span>
                    @endif
                </div>

                <div class="sepet-bilgi">
                    <p class="sepet-ad">{{ $kalem->name }}</p>
                    @if ($kalem->variant_label)
                        <p class="sepet-varyant">{{ $kalem->variant_label }}</p>
                    @endif
                    <p class="sepet-sku">{{ $kalem->sku }}</p>
                </div>

                <div class="sepet-tutar">
                    <span>× {{ $kalem->quantity }}</span>
                    <span>{{ number_format((float) $kalem->line_total, 2, ',', '.') }} TL</span>
                </div>
            </div>
        @endforeach

        <div class="siparis-toplam">
            <div class="ozet-satir">
                <span>Ara toplam</span>
                <span>{{ number_format((float) $order->subtotal, 2, ',', '.') }} TL</span>
            </div>
            @if ((float) $order->discount_total > 0)
                <div class="ozet-satir ozet-indirim">
                    <span>İndirim</span>
                    <span>-{{ number_format((float) $order->discount_total, 2, ',', '.') }} TL</span>
                </div>
            @endif
            <div class="ozet-satir">
                <span>Kargo</span>
                <span>
                    {{ (float) $order->shipping_total > 0
                        ? number_format((float) $order->shipping_total, 2, ',', '.') . ' TL'
                        : 'Ücretsiz' }}
                </span>
            </div>
            <div class="ozet-satir ozet-toplam">
                <span>Toplam</span>
                <span>{{ number_format((float) $order->grand_total, 2, ',', '.') }} TL</span>
            </div>
            @if ((float) $order->refunded_total > 0)
                <div class="ozet-satir ozet-indirim">
                    <span>İade edilen</span>
                    <span>{{ number_format((float) $order->refunded_total, 2, ',', '.') }} TL</span>
                </div>
            @endif
        </div>
    </section>

    {{-- Değerlendirme — yalnız teslim edilmiş siparişte --}}
    @if ($degerlendirme->isNotEmpty())
        <section class="bolum" id="degerlendirme">
            <div class="bolum-basi">
                <h2>Ürünleri Değerlendirin</h2>
            </div>

            @foreach ($degerlendirme as $d)
                <div class="degerlendir">
                    <p class="degerlendir-ad">{{ $d['ad'] }}</p>

                    @if ($d['yorum'])
                        <p class="degerlendir-durum">
                            <span class="yildiz" aria-label="{{ $d['yorum']->rating }} / 5">{{ str_repeat('★', $d['yorum']->rating) }}<span class="yildiz-bos">{{ str_repeat('★', 5 - $d['yorum']->rating) }}</span></span>
                            {{ $d['yorum']->status === 'approved' ? 'Yayında — teşekkürler.' : ($d['yorum']->status === 'rejected' ? 'Yayımlanmadı.' : 'Onay bekliyor.') }}
                        </p>
                    @else
                        <details class="degerlendir-ac" @if (old('product_id') == $d['product_id']) open @endif>
                            <summary>Değerlendirme yaz</summary>

                            <form method="POST"
                                  action="{{ \Illuminate\Support\Facades\URL::signedRoute('order.review', ['order' => $order->number]) }}"
                                  class="degerlendir-formu">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $d['product_id'] }}">

                                <fieldset class="puan-sec">
                                    <legend class="etiket">Puanınız</legend>
                                    {{-- Sağdan sola dizilir: CSS ~ seçicisiyle üzerine gelinen
                                         yıldız ve solundakiler dolu görünür. JS gerekmez. --}}
                                    @for ($p = 5; $p >= 1; $p--)
                                        <input type="radio" name="puan" value="{{ $p }}"
                                               id="puan-{{ $d['product_id'] }}-{{ $p }}"
                                               @checked(old('product_id') == $d['product_id'] && old('puan') == $p) required>
                                        <label for="puan-{{ $d['product_id'] }}-{{ $p }}" title="{{ $p }} / 5">
                                            <span class="gorunmez">{{ $p }} yıldız</span>★
                                        </label>
                                    @endfor
                                </fieldset>

                                <div class="alan">
                                    <label for="baslik-{{ $d['product_id'] }}">Başlık (isteğe bağlı)</label>
                                    <input type="text" id="baslik-{{ $d['product_id'] }}" name="baslik" maxlength="120" class="metin-girdi"
                                           value="{{ old('product_id') == $d['product_id'] ? old('baslik') : '' }}">
                                </div>

                                <div class="alan">
                                    <label for="yorum-{{ $d['product_id'] }}">Yorumunuz</label>
                                    <textarea id="yorum-{{ $d['product_id'] }}" name="yorum" rows="4" minlength="10" maxlength="1500" required class="metin-girdi">{{ old('product_id') == $d['product_id'] ? old('yorum') : '' }}</textarea>
                                </div>

                                @if (old('product_id') == $d['product_id'] && $errors->any())
                                    <p class="uyari uyari-hata">{{ $errors->first() }}</p>
                                @endif

                                <p class="degerlendir-not">
                                    Ürün sayfasında adınız "{{ \App\Models\ProductReview::gorunenAd($order->customer_name) }}" olarak görünür.
                                    Yorumlar onaydan sonra yayımlanır.
                                </p>

                                <button type="submit" class="dugme">Gönder</button>
                            </form>
                        </details>
                    @endif
                </div>
            @endforeach
        </section>
    @endif

    {{-- Mevcut talepler --}}
    @if ($order->returnRequests->isNotEmpty())
        <section class="bolum">
            <div class="bolum-basi">
                <h2>İade / Değişim Talepleriniz</h2>
            </div>

            @foreach ($order->returnRequests as $talep)
                <div class="talep-satir">
                    <div>
                        <p class="talep-no">{{ $talep->number }}</p>
                        <p class="talep-tur">
                            {{ $talep->is_exchange ? 'Değişim' : 'İade' }} ·
                            {{ $talep->created_at->format('d.m.Y') }}
                        </p>
                        @if ($talep->status === 'rejected' && $talep->admin_note)
                            <p class="talep-ret">{{ $talep->admin_note }}</p>
                        @endif
                    </div>
                    <span class="talep-durum">{{ $talep->status_label }}</span>
                </div>
            @endforeach
        </section>
    @endif

    {{-- Yeni talep --}}
    <section class="bolum">
        <div class="bolum-basi">
            <h2>İade / Değişim</h2>
        </div>

        @if (! $iadeAcilabilir)
            <div class="bos-durum">
                @if ($order->payment_status !== 'paid')
                    <p>Ödemesi tamamlanmamış sipariş için talep açılamaz.</p>
                @else
                    <p>
                        Cayma hakkı süresi dolmuş.
                        @if ($caymaSonu)
                            Son gün {{ $caymaSonu->format('d.m.Y') }} idi.
                        @endif
                    </p>
                @endif
            </div>

        @elseif ($iadeEdilebilir->sum() < 1)
            <div class="bos-durum">
                <p>Bu siparişteki tüm ürünler için talep açılmış.</p>
            </div>

        @else
            @if ($caymaSonu)
                <p class="cayma-not">
                    Talep açma süreniz <strong>{{ $caymaSonu->format('d.m.Y') }}</strong> tarihinde doluyor.
                </p>
            @endif

            {{-- İmzalı adres şart: rota `signed` grubunda. route() ile üretilen
                 imzasız adres her gönderimde 403 veriyordu (testler adresi elle
                 imzaladığı için yakalanmamıştı). --}}
            <form method="POST" action="{{ \Illuminate\Support\Facades\URL::signedRoute('order.return', ['order' => $order->number]) }}" class="iade-formu">
                @csrf

                <fieldset class="form-blok">
                    <legend class="etiket">Ürünler</legend>

                    @foreach ($order->items as $kalem)
                        @php($kalan = $iadeEdilebilir[$kalem->id] ?? 0)

                        <div class="iade-kalem {{ $kalan < 1 ? 'iade-kalem-kapali' : '' }}">
                            <label class="onay-kutu">
                                <input type="checkbox"
                                       name="kalemler[{{ $kalem->id }}][sec]"
                                       value="1"
                                       @disabled($kalan < 1)>
                                <span>
                                    <strong>{{ $kalem->name }}</strong>
                                    @if ($kalem->variant_label)
                                        <br>{{ $kalem->variant_label }}
                                    @endif
                                    @if ($kalan < 1)
                                        <br><em>Bu ürün için talep açılmış.</em>
                                    @endif
                                </span>
                            </label>

                            @if ($kalan > 0)
                                <div class="iade-kalem-alanlar">
                                    <label>
                                        Adet
                                        <input type="number"
                                               name="kalemler[{{ $kalem->id }}][adet]"
                                               value="1" min="1" max="{{ $kalan }}"
                                               class="adet-girdi">
                                    </label>

                                    @if ($degisimSecenekleri[$kalem->id]->isNotEmpty())
                                        <label class="degisim-secim">
                                            Değişimde istediğiniz beden
                                            <select name="kalemler[{{ $kalem->id }}][degisim_varyant]"
                                                    class="metin-girdi">
                                                <option value="">Seçiniz (yalnızca değişimde)</option>
                                                @foreach ($degisimSecenekleri[$kalem->id] as $varyant)
                                                    <option value="{{ $varyant->id }}"
                                                            @disabled($varyant->available_stock < 1)>
                                                        {{ $varyant->label }}
                                                        @if ($varyant->available_stock < 1) — tükendi @endif
                                                    </option>
                                                @endforeach
                                            </select>
                                        </label>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </fieldset>

                <fieldset class="form-blok">
                    <legend class="etiket">Talep</legend>

                    <div class="alan-ikili">
                        <div class="alan">
                            <label for="tur">Ne yapmak istiyorsunuz?</label>
                            <select id="tur" name="tur" class="metin-girdi" required>
                                <option value="return">İade — ücret iadesi</option>
                                <option value="exchange">Değişim — başka beden</option>
                            </select>
                        </div>

                        <div class="alan">
                            <label for="gerekce">Gerekçe</label>
                            <select id="gerekce" name="gerekce" class="metin-girdi" required>
                                <option value="beden">Beden tutmadı</option>
                                <option value="kusurlu">Ürün kusurlu</option>
                                <option value="yanlis-urun">Yanlış ürün geldi</option>
                                <option value="vazgectim">Vazgeçtim</option>
                                <option value="diger">Diğer</option>
                            </select>
                        </div>
                    </div>

                    <div class="alan">
                        <label for="not">Eklemek istedikleriniz (isteğe bağlı)</label>
                        <textarea id="not" name="not" rows="3" class="metin-girdi"></textarea>
                    </div>
                </fieldset>

                <button type="submit" class="dugme">Talep oluştur</button>

                <p class="ozet-not" style="text-align:left; margin-top:14px">
                    Talebinizi aldıktan sonra ürünü nasıl göndereceğinizi e-posta ile ileteceğiz.
                    Ürünün kullanılmamış ve etiketlerinin sökülmemiş olması gerekir.
                </p>
            </form>
        @endif
    </section>
</div>
@endsection
