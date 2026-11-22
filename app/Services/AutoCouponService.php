<?php

namespace App\Services;

use App\Mail\AutoCouponMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Webkul\CartRule\Repositories\CartRuleCouponRepository;
use Webkul\CartRule\Repositories\CartRuleRepository;
use Webkul\Core\Models\Channel;
use Webkul\Customer\Models\CustomerGroup;
use Webkul\Sales\Contracts\Order;
use Webkul\Sales\Models\Order as OrderModel;

class AutoCouponService
{
    /** Estados que no cuentan como compra efectiva. */
    protected const EXCLUDED_STATUSES = ['canceled', 'closed', 'fraud'];

    public function __construct(
        protected CartRuleRepository $cartRuleRepository,
        protected CartRuleCouponRepository $cartRuleCouponRepository,
    ) {}

    /**
     * Revisa lo acumulado por el cliente y genera los cupones que le correspondan.
     *
     * @return array<int, \stdClass> cupones creados
     */
    public function handleOrder(Order $order): array
    {
        if (! config('examen.coupons.enabled') || ! $order->customer_id) {
            return [];
        }

        $thresholdBase = $this->thresholdInBaseCurrency();

        if ($thresholdBase <= 0) {
            return [];
        }

        $total = $this->accumulatedTotal((int) $order->customer_id);
        $earned = (int) floor($total / $thresholdBase);
        $issued = DB::table('customer_auto_coupons')->where('customer_id', $order->customer_id)->count();

        $created = [];

        // Un cupon por cada multiplo del umbral alcanzado (L 10,000, L 20,000, ...), maximo 3 de una vez.
        for ($milestone = $issued + 1; $milestone <= min($earned, $issued + 3); $milestone++) {
            $created[] = $this->issueCoupon($order, $milestone, $total);
        }

        return $created;
    }

    /**
     * Suma de compras del cliente en moneda base, sin contar pedidos cancelados.
     */
    public function accumulatedTotal(int $customerId): float
    {
        return (float) OrderModel::query()
            ->where('customer_id', $customerId)
            ->whereNotIn('status', self::EXCLUDED_STATUSES)
            ->sum('base_grand_total');
    }

    /**
     * El umbral esta definido en Lempiras; se pasa a la moneda base usando el tipo de cambio de Bagisto.
     */
    public function thresholdInBaseCurrency(): float
    {
        $threshold = (float) config('examen.coupons.threshold');
        $currency = config('examen.coupons.threshold_currency');

        if (! $currency || $currency === core()->getBaseCurrencyCode()) {
            return $threshold;
        }

        return (float) core()->convertToBasePrice($threshold, $currency);
    }

    protected function issueCoupon(Order $order, int $milestone, float $totalBase): \stdClass
    {
        $percent = config('examen.coupons.percent');
        $days = config('examen.coupons.valid_days');
        $code = $this->uniqueCode();
        $customerName = trim($order->customer_first_name.' '.$order->customer_last_name);
        $expiresAt = now()->addDays($days);

        $cartRule = $this->cartRuleRepository->create([
            'name' => "Cupón automático {$percent}% - {$customerName} (hito {$milestone})",
            'description' => "Generado automáticamente al acumular compras. Cliente #{$order->customer_id}.",
            'starts_from' => null,
            'ends_till' => $expiresAt->format('Y-m-d H:i:s'),
            'status' => 1,
            'coupon_type' => 1,
            'use_auto_generation' => 0,
            'coupon_code' => $code,
            'usage_per_customer' => 1,
            'uses_per_coupon' => 1,
            'condition_type' => 1,
            'conditions' => [],
            'end_other_rules' => 0,
            'action_type' => 'by_percent',
            'discount_amount' => $percent,
            'discount_quantity' => 0, // 0 = sin limite de unidades con descuento
            'discount_step' => '1',
            'apply_to_shipping' => 0,
            'free_shipping' => 0,
            'sort_order' => 0,
            'channels' => Channel::pluck('id')->all(),
            'customer_groups' => CustomerGroup::pluck('id')->all(),
        ]);

        DB::table('customer_auto_coupons')->insert([
            'customer_id' => $order->customer_id,
            'cart_rule_id' => $cartRule->id,
            'order_id' => $order->id,
            'code' => $code,
            'milestone' => $milestone,
            'accumulated_total' => $totalBase,
            'expires_at' => $expiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $coupon = (object) [
            'code' => $code,
            'percent' => $percent,
            'expires_at' => $expiresAt,
            'milestone' => $milestone,
        ];

        $this->sendEmail($order, $coupon);

        return $coupon;
    }

    protected function sendEmail(Order $order, \stdClass $coupon): void
    {
        try {
            Mail::send(new AutoCouponMail($order, $coupon));
        } catch (\Throwable $e) {
            Log::error('Cupón automático: no se pudo enviar el correo: '.$e->getMessage());
        }
    }

    protected function uniqueCode(): string
    {
        do {
            $code = strtoupper(config('examen.coupons.prefix').'-'.Str::random(8));
        } while ($this->cartRuleCouponRepository->findOneByField('code', $code));

        return $code;
    }
}
