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

            girdi.disabled = !varMi;
            etiket.classList.toggle('secenek-yok', !varMi);

            if (!varMi && girdi.checked) {
                girdi.checked = false;
            }
        });

        girdiler.forEach((g) => {
            g.closest('.secenek').classList.toggle('secenek-secili', g.checked);
        });

        const guncelSecim = secilenler();
        const varyant = tamEslesme(guncelSecim);

        if (varyant) {
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
            dugme.textContent = 'Seçim yapın';
            durum.textContent = '';
            durum.className = 'secim-durum';
        }

        renkGaleriGuncelle(guncelSecim);
    }

    /* Renk seçilince galeri o renge geçer */
    function renkGaleriGuncelle(secili) {
        const ana = document.getElementById('galeri-ana');
        if (!ana) return;

        for (const deger of secili) {
            const yollar = galeri[deger];
            if (yollar && yollar.length) {
                ana.src = ana.dataset.taban
                    ? ana.dataset.taban + yollar[0]
                    : ana.src.replace(/\/storage\/.*$/, '/storage/' + yollar[0]);
                return;
            }
        }
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
