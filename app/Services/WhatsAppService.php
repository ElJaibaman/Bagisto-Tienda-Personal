<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Webkul\Sales\Contracts\Order;

class WhatsAppService
{
    /**
     * Avisa por WhatsApp que se registro una nueva compra.
     */
    public function notifyNewOrder(Order $order): void
    {
        if (! config('examen.whatsapp.enabled')) {
            return;
        }

        $recipients = config('examen.whatsapp.admin_numbers', []);

        if (config('examen.whatsapp.notify_customer')) {
            $customerPhone = $order->billing_address?->phone ?? $order->shipping_address?->phone;

            if ($customerPhone) {
                $recipients[] = $customerPhone;
            }
        }

        $recipients = collect($recipients)
            ->map(fn ($number) => $this->normalizeNumber($number))
            ->filter()
            ->unique();

        foreach ($recipients as $to) {
            $this->send($to, $order);
        }
    }

    /**
     * Envia el mensaje a un numero. No lanza excepciones: una falla de WhatsApp nunca debe afectar la compra.
     */
    public function send(string $to, Order $order): bool
    {
        $config = config('examen.whatsapp');

        if (! $config['token'] || ! $config['phone_number_id']) {
            Log::warning('WhatsApp: falta WHATSAPP_TOKEN o WHATSAPP_PHONE_NUMBER_ID.');

            return false;
        }

        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            $config['api_version'],
            $config['phone_number_id']
        );

        try {
            $response = Http::withToken($config['token'])
                ->timeout(15)
                ->post($url, $this->buildPayload($to, $order));

            if ($response->failed()) {
                Log::error('WhatsApp: la API respondio con error.', [
                    'to' => $to,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return false;
            }

            Log::info('WhatsApp: mensaje enviado.', ['to' => $to, 'order' => $order->increment_id]);

            return true;
        } catch (\Throwable $e) {
            Log::error('WhatsApp: no se pudo enviar el mensaje: '.$e->getMessage());

            return false;
        }
    }

    public function buildPayload(string $to, Order $order): array
    {
        $config = config('examen.whatsapp');

        $base = ['messaging_product' => 'whatsapp', 'to' => $to];

        if ($config['mode'] === 'template') {
            $template = [
                'name' => $config['template'],
                'language' => ['code' => $config['template_language']],
            ];

            if ($config['template_with_params']) {
                $template['components'] = [[
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => (string) $order->increment_id],
                        ['type' => 'text', 'text' => trim($order->customer_first_name.' '.$order->customer_last_name)],
                        ['type' => 'text', 'text' => $this->formattedTotal($order)],
                    ],
                ]];
            }

            return $base + ['type' => 'template', 'template' => $template];
        }

        return $base + [
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => $this->buildText($order)],
        ];
    }

    public function buildText(Order $order): string
    {
        return implode("\n", [
            '*'.config('examen.store_name').'*: nueva compra registrada',
            'Pedido: #'.$order->increment_id,
            'Cliente: '.trim($order->customer_first_name.' '.$order->customer_last_name),
            'Total: '.$this->formattedTotal($order),
            'Articulos: '.(int) $order->total_qty_ordered,
            'Pago: '.($order->payment?->method_title ?? $order->payment?->method ?? 'N/D'),
        ]);
    }

    protected function formattedTotal(Order $order): string
    {
        return core()->formatPrice($order->grand_total, $order->order_currency_code);
    }

    /**
     * Deja solo digitos y agrega el codigo de pais si el numero es local (8 digitos en Honduras).
     */
    public function normalizeNumber(?string $number): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $number);

        if ($digits === '') {
            return null;
        }

        $cc = config('examen.whatsapp.default_country_code');

        if ($cc && strlen($digits) <= 8) {
            $digits = $cc.$digits;
        }

        return $digits;
    }
}
