<?php

namespace App\Listeners;

use App\Services\AutoCouponService;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Webkul\Sales\Contracts\Order;

/**
 * Se ejecuta con el evento checkout.order.save.after de Bagisto.
 * Todo corre despues del commit y con try/catch: un error aqui jamas debe tumbar la compra.
 */
class OrderPlacedListener
{
    public function __construct(
        protected WhatsAppService $whatsApp,
        protected AutoCouponService $coupons,
    ) {}

    public function handle(Order $order): void
    {
        DB::afterCommit(function () use ($order) {
            try {
                $this->whatsApp->notifyNewOrder($order);
            } catch (\Throwable $e) {
                Log::error('OrderPlacedListener (WhatsApp): '.$e->getMessage());
            }

            try {
                $this->coupons->handleOrder($order);
            } catch (\Throwable $e) {
                Log::error('OrderPlacedListener (cupones): '.$e->getMessage());
            }
        });
    }
}
