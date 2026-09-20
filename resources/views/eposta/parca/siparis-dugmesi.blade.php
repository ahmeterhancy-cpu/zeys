{{--
    Siparis sayfasinin IMZALI baglantisi.

    Musteri numarasini elle girmek zorunda kalmasin diye. Imza olmadan
    o sayfa acilmiyor (siparis numarasi tahmin edilebilir), bu yuzden
    baglanti burada uretiliyor.

    Sure sinirsiz: musteri e-postayi aylar sonra acip iade talebi
    acabilmeli. Imza kaldiginca adres calisir.
--}}
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:28px 0 0;">
    <tr>
        <td style="background:#1c1a15;">
            <a href="{{ \Illuminate\Support\Facades\URL::signedRoute('order.show', ['order' => $order->number]) }}"
               style="display:inline-block; padding:15px 30px; color:#ffffff; text-decoration:none; font-family:Arial,Helvetica,sans-serif; font-size:12px; letter-spacing:2px; text-transform:uppercase;">
                Siparişimi görüntüle
            </a>
        </td>
    </tr>
</table>
