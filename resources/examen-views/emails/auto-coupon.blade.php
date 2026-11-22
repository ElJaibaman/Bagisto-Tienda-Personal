@component('shop::emails.layout')
    <div style="margin-bottom: 34px;">
        <p style="font-weight: bold;font-size: 20px;color: #121A26;line-height: 24px;margin-bottom: 24px">
            Hola {{ $order->customer_first_name }}, ¡gracias por tu preferencia! ☕
        </p>

        <p style="font-size: 16px;color: #384860;line-height: 24px;">
            Por tus compras acumuladas en {{ config('examen.store_name') }} te regalamos un cupón de
            <strong>{{ rtrim(rtrim(number_format($coupon->percent, 2), '0'), '.') }}% de descuento</strong> para tu próxima compra.
        </p>
    </div>

    <div style="text-align:center;margin:30px 0;padding:24px;border:2px dashed #6B3E26;border-radius:8px;">
        <p style="margin:0 0 8px;font-size:14px;color:#384860;">Tu código</p>
        <p style="margin:0;font-size:28px;font-weight:700;letter-spacing:3px;color:#6B3E26;">{{ $coupon->code }}</p>
    </div>

    <p style="font-size: 16px;color: #384860;line-height: 24px;margin-bottom: 40px">
        Escríbelo en el carrito, en el campo de cupón. Es de un solo uso y vence el
        {{ $coupon->expires_at->format('d/m/Y') }}.
    </p>
@endcomponent
