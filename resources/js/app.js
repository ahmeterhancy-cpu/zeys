/*
 * Zeys Fashion House — vitrin JS.
 * Calisma zamani bagimliligi YOK.
 */

/*
 * Belirme efekti.
 *
 * KURAL: icerik once gizlenip sonra gosterilmez. CSS'teki gizleme yalnizca
 * <body class="js-acik"> altinda gecerli; bu sinifi da ancak IntersectionObserver
 * gercekten varsa ekliyoruz. Boylece JS calismazsa, eski bir tarayicida
 * IO yoksa ya da bir hata olursa sayfa TAM GORUNUR kalir.
 */
(function () {
    if (!('IntersectionObserver' in window)) return;

    const ogeler = document.querySelectorAll('.belir');
    if (!ogeler.length) return;

    document.body.classList.add('js-acik');

    const gozlemci = new IntersectionObserver(
        (girisler) => {
            girisler.forEach((giris) => {
                if (!giris.isIntersecting) return;
                giris.target.classList.add('gorundu');
                gozlemci.unobserve(giris.target);
            });
        },
        { rootMargin: '0px 0px -8% 0px', threshold: 0.05 }
    );

    ogeler.forEach((oge) => gozlemci.observe(oge));

    /*
     * Emniyet kemeri: gozlemci herhangi bir sebeple tetiklenmezse
     * (programatik kaydirma, sekme arkada acilmasi, gizli sekme)
     * 1.5 sn sonra her sey gorunur olur.
     */
    window.setTimeout(() => {
        ogeler.forEach((oge) => oge.classList.add('gorundu'));
    }, 1500);
})();

/*
 * Sekmeler.
 * JS yoksa tüm paneller alt alta görünür (hiçbiri gizlenmez). JS varsa
 * ilk sekme dışındakiler [hidden] olur; ok tuşlarıyla gezilebilir.
 */
document.querySelectorAll('[data-sekmeler]').forEach((kap) => {
    const sekmeler = [...kap.querySelectorAll('[role="tab"]')];
    const sec = (hedef, odakla = false) => {
        sekmeler.forEach((s) => {
            const secili = s === hedef;
            s.setAttribute('aria-selected', secili ? 'true' : 'false');
            s.tabIndex = secili ? 0 : -1;
            const panel = document.getElementById(s.getAttribute('aria-controls'));
            if (panel) panel.hidden = !secili;
        });
        if (odakla) hedef.focus();
    };

    sekmeler.forEach((s, i) => {
        s.addEventListener('click', () => sec(s));
        s.addEventListener('keydown', (e) => {
            if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
            const yon = e.key === 'ArrowRight' ? 1 : -1;
            sec(sekmeler[(i + yon + sekmeler.length) % sekmeler.length], true);
        });
    });

    const baslangic = sekmeler.find((s) => s.getAttribute('aria-selected') === 'true') || sekmeler[0];
    if (baslangic) sec(baslangic);
});

/* Başka yerden bir sekmeyi açan bağlantılar (ör. ürün puanı → Değerlendirmeler) */
document.querySelectorAll('[data-sekme-ac]').forEach((bag) => {
    bag.addEventListener('click', () => document.getElementById(bag.dataset.sekmeAc)?.click());
});

/* Ürün şeridi okları: bir ekran genişliği kaydırır, uçta ok kapanır */
document.querySelectorAll('[data-serit]').forEach((serit) => {
    const ray = serit.querySelector('.urun-serit-ray');
    const sol = serit.querySelector('[data-serit-yon="-1"]');
    const sag = serit.querySelector('[data-serit-yon="1"]');
    if (!ray) return;

    const guncelle = () => {
        if (sol) sol.disabled = ray.scrollLeft <= 2;
        if (sag) sag.disabled = ray.scrollLeft + ray.clientWidth >= ray.scrollWidth - 2;
    };

    serit.querySelectorAll('[data-serit-yon]').forEach((dgm) => {
        dgm.addEventListener('click', () => {
            ray.scrollBy({ left: Number(dgm.dataset.seritYon) * ray.clientWidth, behavior: 'smooth' });
        });
    });

    ray.addEventListener('scroll', guncelle, { passive: true });
    window.addEventListener('resize', guncelle);
    guncelle();
});

/*
 * Slayt: noktalar hangi slaytta olunduğunu gösterir; hareket azaltma
 * istenmediyse 6 sn'de bir kendiliğinden ilerler, üzerine gelince durur.
 */
document.querySelectorAll('[data-slayt]').forEach((slayt) => {
    const ray = slayt.querySelector('.slayt-ray');
    const noktalar = [...slayt.querySelectorAll('.slayt-noktalar a')];
    if (!ray || noktalar.length < 2) return;

    const sira = () => Math.round(ray.scrollLeft / ray.clientWidth);
    const git = (i) => ray.scrollTo({ left: i * ray.clientWidth, behavior: 'smooth' });

    noktalar.forEach((n, i) => n.addEventListener('click', (e) => {
        e.preventDefault();
        git(i);
    }));

    ray.addEventListener('scroll', () => {
        const aktif = sira();
        noktalar.forEach((n, i) => n.classList.toggle('aktif', i === aktif));
    }, { passive: true });

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    let dur = false;
    slayt.addEventListener('mouseenter', () => { dur = true; });
    slayt.addEventListener('mouseleave', () => { dur = false; });
    slayt.addEventListener('focusin', () => { dur = true; });
    slayt.addEventListener('focusout', () => { dur = false; });

    window.setInterval(() => {
        if (dur || document.hidden) return;
        git((sira() + 1) % noktalar.length);
    }, 6000);
});

/* "Yukarı" düğmesi bir ekran aşağı inince belirir */
(function () {
    const dugme = document.querySelector('.yukari');
    if (!dugme) return;
    const bak = () => dugme.classList.toggle('gorunur', window.scrollY > window.innerHeight * 0.8);
    window.addEventListener('scroll', bak, { passive: true });
    bak();
})();

/* Mobil menü açıkken dışarı (karartılmış alana) tıklayınca kapansın */
document.addEventListener('click', (e) => {
    const menu = document.querySelector('.mobil-menu[open]');
    if (menu && !menu.querySelector('.mobil-menu-panel').contains(e.target) && !menu.querySelector('summary').contains(e.target)) {
        menu.open = false;
    }
});

/* Mağaza süzgeçleri telefonda kapalı başlasın (masaüstünde her zaman açık) */
if (window.matchMedia('(max-width: 991px)').matches) {
    document.querySelectorAll('.magaza-yan-kap[open]').forEach((d) => { d.open = false; });
}

/* Sıralama seçimi değişince liste yenilensin (JS yoksa "Uygula" düğmesi var) */
document.querySelectorAll('[data-otomatik-gonder]').forEach((alan) => {
    alan.addEventListener('change', () => alan.form?.submit());
});
