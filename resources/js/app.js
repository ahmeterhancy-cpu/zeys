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
