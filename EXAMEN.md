# Café Catracho: Examen Negocio Bagisto II

Tienda en línea de café hondureño sobre Bagisto 2.3. Tema propio: 3 categorías y 8 productos
(Marcala, Copán, Opalaca, Montecillos, Pico Bonito, taza lenca, prensa francesa y kit de degustación).

## Puesta en marcha

```bash
php artisan migrate                 # crea la tabla customer_auto_coupons
php artisan storage:link
php artisan examen:setup --seed     # activa PayPal, correos, moneda HNL y carga el catálogo
php artisan serve
```

Si la base todavía no tiene Bagisto instalado, corre antes `php artisan bagisto:install`
(idioma es, moneda USD, y agrega HNL como moneda permitida). Si no aparece el catálogo, repite
`php artisan indexer:index --mode=full`.

Todas las llaves se configuran en el `.env` (bloque "EXAMEN"). Después de editarlo: `php artisan config:clear`.

## Dónde está cada requisito

| # | Requisito | Código |
|---|-----------|--------|
| 1 | WhatsApp Business | `app/Services/WhatsAppService.php`, `app/Listeners/OrderPlacedListener.php` |
| 2 | Cupones automáticos | `app/Services/AutoCouponService.php`, `app/Mail/AutoCouponMail.php`, migración `customer_auto_coupons` |
| 3 | Chat Tawk.to | `resources/views/examen/tawk.blade.php` (se inyecta con el hook `bagisto.shop.layout.body.after`) |
| 4 | PayPal Sandbox | PayPal Smart Button nativo de Bagisto, activado por `examen:setup` |
| 5 | Correo de registro | Correos nativos de Bagisto (cliente y administrador), activados por `examen:setup` |

El enlace de todo está en `app/Providers/ExamenServiceProvider.php`.

## 1. WhatsApp Business (Cloud API de Meta)

1. En developers.facebook.com crea una app tipo Business y agrega el producto WhatsApp.
2. En "API Setup" copia el **Phone number ID** y un **token** (el temporal dura 24 h; para uso continuo crea un usuario del sistema con token permanente).
3. Agrega tu número como destinatario de prueba (el número de prueba de Meta solo escribe a números verificados).
4. `.env`:
   ```
   WHATSAPP_ENABLED=true
   WHATSAPP_TOKEN=...
   WHATSAPP_PHONE_NUMBER_ID=...
   WHATSAPP_ADMIN_NUMBERS=504XXXXXXXX
   ```
5. Modos:
   - `WHATSAPP_MODE=template` (por defecto): usa la plantilla `hello_world` que Meta trae aprobada. Sirve para probar, pero el texto es fijo.
   - Con una plantilla propia aprobada con 3 variables ({{1}} pedido, {{2}} cliente, {{3}} total): `WHATSAPP_TEMPLATE=mi_plantilla`, `WHATSAPP_TEMPLATE_LANGUAGE=es`, `WHATSAPP_TEMPLATE_WITH_PARAMS=true`.
   - `WHATSAPP_MODE=text`: mensaje libre con pedido, cliente, total, artículos y método de pago. Meta solo lo entrega si el destinatario te escribió en las últimas 24 h.
6. `WHATSAPP_NOTIFY_CUSTOMER=true` también avisa al cliente (teléfono de su dirección de facturación).

El envío corre después del commit de la orden y dentro de try/catch: si WhatsApp falla, la compra no se afecta (revisa `storage/logs/laravel.log`).

## 2. Cupones automáticos

Cada vez que se registra una compra, se suma lo que el cliente lleva comprado (sin contar pedidos cancelados, cerrados o fraude).
Por cada múltiplo de **L 10,000** acumulado se crea un cupón de **10 %**:

- Código único `CATRACHO-XXXXXXXX`, de un solo uso, vence a los 30 días.
- Se crea como regla de carrito normal de Bagisto (Marketing > Promociones > Reglas de carrito) y se envía por correo al cliente.
- El historial queda en la tabla `customer_auto_coupons`.
- Solo aplica a clientes registrados (los invitados no acumulan).
- El umbral está en Lempiras y se convierte a la moneda base con el tipo de cambio de Bagisto (`HNL_EXCHANGE_RATE`, que `examen:setup` guarda). Ajusta `AUTO_COUPON_THRESHOLD`, `AUTO_COUPON_PERCENT` y `AUTO_COUPON_VALID_DAYS` en el `.env`.

## 3. Chat en vivo con Tawk.to

1. Crea cuenta en tawk.to y una Property.
2. En Administration > Channels > Chat Widget copia el Property ID y el Widget ID del script (`embed.tawk.to/<PROPERTY_ID>/<WIDGET_ID>`).
3. `.env`: `TAWK_ENABLED=true`, `TAWK_PROPERTY_ID=...`, `TAWK_WIDGET_ID=...`.
4. El administrador responde desde el panel de tawk.to o su app móvil.

## 4. PayPal (Sandbox)

1. developer.paypal.com > Apps & Credentials > modo **Sandbox** > Create App.
2. `.env`: `PAYPAL_SANDBOX_CLIENT_ID` y `PAYPAL_SANDBOX_CLIENT_SECRET`, luego `php artisan examen:setup`.
3. Para pagar, usa una cuenta compradora de Sandbox (Sandbox > Accounts, tipo Personal).
4. Se activa el método "PayPal Smart Button" en modo sandbox y se apaga "PayPal Standard" para que haya un solo botón. También puedes ver o cambiar esto en Admin > Configuración > Ventas > Métodos de pago.

PayPal no procesa Lempiras (HNL). La tienda tiene USD como moneda base, así que el cobro es en dólares aunque el cliente vea los precios en HNL. Los precios del catálogo se definen en Lempiras y se guardan convertidos con `HNL_EXCHANGE_RATE`.

## 5. Correos de registro

`examen:setup` activa en Bagisto:

- Correo de bienvenida al cliente que se registra.
- Aviso al administrador (`ADMIN_MAIL_ADDRESS`) por cada nuevo cliente.

El envío usa el SMTP del `.env` (Gmail con contraseña de aplicación). Si cambias el correo del administrador, también puedes hacerlo en Admin > Configuración > Correo electrónico.

## Prueba rápida de punta a punta

1. Regístrate en `/customer/register`: llegan 2 correos (cliente y administrador).
2. Compra un producto con PayPal Sandbox: llega el WhatsApp.
3. Compra hasta acumular L 10,000 (aprox. 377 USD con el cambio por defecto): llega el correo con el cupón. Pruébalo en el carrito.
4. Abre cualquier página de la tienda: aparece el chat de Tawk.to.

## Seguridad

El `.env` tiene la contraseña de aplicación de Gmail y las llaves de las integraciones. No lo subas a un repositorio.
