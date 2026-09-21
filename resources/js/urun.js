/*
 * Ürün sayfası: Beden × Renk seçimi.
 *
 * Sunucudaki VariantMatrix::availableValueIds ile AYNI kuralı uygular:
 * bir değer, onu içeren ve seçili değerlerin tamamıyla uyuşan stoklu bir
 * varyant varsa seçilebilirdir. İkisi ayrışırsa müşteri stokta olmayan
 * kombinasyonu seçebilir hâle gelir, bu yüzden kural tek cümlede tutuldu.
 *
 * Fiyat burada yalnızca GÖSTERİLİR. Sepete ekleme sunucuda fiyatı
 * yeniden okur; istemciden gelen tutara güvenilmez.
 */
(function () {
    const veriEtiketi = document.getElementById('varyant-verisi');
    const form = document.getElementById('sepet-formu');

    if (!veriEtiketi || !form) return;

    let varyantlar = [];
    let galeri = {};

    try {
        varyantlar = JSON.parse(veriEtiketi.textContent) || [];
        const g = document.getElementById('galeri-verisi');
        galeri = g ? JSON.parse(g.textContent) || {} : {};
    } catch (e) {
        // Veri bozuksa seçici devre dışı kalır ama sayfa okunur durumda kalmalı
        return;
    }

    const eksenler = [...document.querySelectorAll('.eksen')];
    const girdiler = [...document.querySelectorAll('.eksen-girdi')];
    const varyantAlani = document.getElementById('variant-id');
    const dugme = document.getElementById('sepete-dugme');
    const durum = document.getElementById('secim-durum');
    const fiyatAlani = document.getElementById('secim-fiyat');
    const adetAlani = document.getElementById('adet');

    /* Hangi değer hangi eksene ait */
    const degerinEkseni = new Map();
    eksenler.forEach((fs) => {
        const eksenId = fs.dataset.eksen;
        fs.querySelectorAll('.eksen-girdi').forEach((g) => {
            degerinEkseni.set(Number(g.value), eksenId);
        });
    });

    const paraBicimi = new Intl.NumberFormat('tr-TR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    function secilenler() {
        return girdiler.filter((g) => g.checked).map((g) => Number(g.value));
    }

    /* Stoğu olan varyantlara göre seçilebilir değerler */
    function secilebilirDegerler(secili) {
        const kume = new Set();

        varyantlar
            .filter((v) => v.stok > 0)
            .forEach((v) => {
                const degerler = v.degerler;

                for (const s of secili) {
                    const eksen = degerinEkseni.get(s);
                    // Varyant o ekseni taşıyorsa ve seçilen değeri içermiyorsa elenir
                    const eksenVar = degerler.some((d) => degerinEkseni.get(d) === eksen);
                    if (eksenVar && !degerler.includes(s)) return;
                }

                degerler.forEach((d) => kume.add(d));
            });

        return kume;
    }

    function tamEslesme(secili) {
        if (secili.length !== eksenler.length) return null;

        return (
            varyantlar.find(
                (v) =>
                    v.degerler.length === secili.length &&
                    secili.every((s) => v.degerler.includes(s))
            ) || null
        );
    }

    function tazele() {
        const secili = secilenler();

        /*
         * Seçilebilirlik hesaplanırken, DEĞERLENDİRİLEN eksenin kendi seçimi
         * dışarıda bırakılır. Aksi hâlde "Siyah" seçiliyken Siyah dışındaki
         * renkler hep sönük kalır ve müşteri renk değiştiremez.
         */
        girdiler.forEach((girdi) => {
            const deger = Number(girdi.value);
            const eksen = degerinEkseni.get(deger);
            const digerSecimler = secili.filter((s) => degerinEkseni.get(s) !== eksen);
            const uygun = secilebilirDegerler(digerSecimler);

            const etiket = girdi.closest('.secenek');
            const varMi = uygun.has(deger);

            /*
             * Stoksuz secenek SONUK gosterilir ama SECILEBILIR kalir.
             *
             * Onceden disabled yapiliyordu; o zaman musteri "Siyah M"yi
             * hic secemiyordu ve "stokta yok - haber ver" bolumu asla
             * gorunmuyordu. Musteri tukenen bedeni secebilmeli ki
             * bildirime kaydolabilsin.
             */
            etiket.classList.toggle('secenek-yok', !varMi);
        });

        girdiler.forEach((g) => {
            g.closest('.secenek').classList.toggle('secenek-secili', g.checked);
        });

        const guncelSecim = secilenler();
        const varyant = tamEslesme(guncelSecim);

        // Tukenmis varyant secilebildigi icin stok kontrolu SART
        if (varyant && varyant.stok > 0) {
            varyantAlani.value = varyant.id;
            dugme.disabled = false;
            dugme.textContent = 'Sepete ekle';
            fiyatAlani.textContent = paraBicimi.format(varyant.fiyat) + ' TL';
            adetAlani.max = Math.min(20, varyant.stok);

            durum.textContent =
                varyant.stok <= 3 ? 'Son ' + varyant.stok + ' adet' : '';
            durum.className = 'secim-durum' + (varyant.stok <= 3 ? ' secim-durum-az' : '');
        } else {
            varyantAlani.value = '';
            dugme.disabled = true;
            dugme.textContent = varyant ? 'Bu beden tükendi' : 'Seçim yapın';
            durum.textContent = '';
            durum.className = 'secim-durum';
        }

        haberVerGuncelle(guncelSecim);

        renkGaleriGuncelle(guncelSecim);
    }

    /*
     * Tukenmis kombinasyon secildiginde "haber ver" bolumu acilir.
     *
     * Tam kombinasyon secilmis ama stoklu varyant bulunamamissa, ayni
     * kombinasyona karsilik gelen STOKSUZ varyant aranir — musteri
     * "Siyah M yok" dedigimiz bedeni bekleyebilmeli.
     */
    function haberVerGuncelle(secili) {
        const bolum = document.getElementById('haber-ver');
        const alan = document.getElementById('haber-ver-varyant');

        if (!bolum || !alan) return;

        if (secili.length !== eksenler.length) {
            bolum.hidden = true;
            alan.value = '';
            return;
        }

        const tam = varyantlar.find(
            (v) =>
                v.degerler.length === secili.length &&
                secili.every((s) => v.degerler.includes(s))
        );

        if (tam && tam.stok < 1) {
            bolum.hidden = false;
            alan.value = tam.id;
        } else {
            bolum.hidden = true;
            alan.value = '';
        }
    }

    /*
     * Renk seçilince ana görsel o rengin ilk fotoğrafına geçer.
     * Seçili rengin fotoğrafı yoksa açılış görseline döner — önceki
     * rengin fotoğrafında takılı kalmasın.
     */
    function renkGaleriGuncelle(secili) {
        const ana = document.getElementById('galeri-ana');
        if (!ana || !ana.dataset.taban) return;

        const uyan = (v) => secili.every((s) => v.degerler.includes(s));

        /*
         * Öncelik:
         *  1. Tam kombinasyon seçildiyse ve o varyantın kendi görseli varsa o.
         *  2. Seçili rengin galerisi (panelde renk başına yüklenenler).
         *  3. Kısmi seçimde, seçilenlerle uyuşan ilk varyantın görseli —
         *     mağaza yalnız varyant görseli yüklediyse renk seçimi yine
         *     görseli değiştirsin.
         */
        if (secili.length === eksenler.length) {
            const tam = varyantlar.find((v) => v.degerler.length === secili.length && uyan(v));
            if (tam && tam.gorsel) {
                ana.src = ana.dataset.taban + tam.gorsel;
                return;
            }
        }

        for (const deger of secili) {
            const yollar = galeri[deger];
            if (yollar && yollar.length) {
                ana.src = ana.dataset.taban + yollar[0];
                return;
            }
        }

        if (secili.length) {
            const gorselli = varyantlar.find((v) => v.gorsel && uyan(v));
            if (gorselli) {
                ana.src = ana.dataset.taban + gorselli.gorsel;
                return;
            }
        }

        if (ana.dataset.ilk) ana.src = ana.dataset.ilk;
    }

    girdiler.forEach((g) => g.addEventListener('change', tazele));
    tazele();

    /* Küçük görsellerden ana görsele */
    document.querySelectorAll('.galeri-kucuk-dugme').forEach((dgm) => {
        dgm.addEventListener('click', () => {
            const ana = document.getElementById('galeri-ana');
            if (ana) ana.src = dgm.dataset.gorsel;
        });
    });

    /* Beden tablosu */
    const tablo = document.getElementById('beden-tablosu');

    if (tablo) {
        document.querySelectorAll('.beden-tablosu-ac').forEach((dgm) => {
            dgm.addEventListener('click', () => {
                if (typeof tablo.showModal === 'function') {
                    tablo.showModal();
                } else {
                    // <dialog> desteklenmiyorsa tabloyu gizlemek yerine göster
                    tablo.setAttribute('open', '');
                }
            });
        });

        tablo.querySelector('.beden-tablosu-kapat')?.addEventListener('click', () => {
            if (typeof tablo.close === 'function') {
                tablo.close();
            } else {
                tablo.removeAttribute('open');
            }
        });

        // Dışına tıklayınca kapansın
        tablo.addEventListener('click', (olay) => {
            if (olay.target === tablo) tablo.close();
        });
    }
})();
