<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Webkul\Sales\Contracts\Order;

class AutoCouponMail extends Mailable
{
    public function __construct(public Order $order, public \stdClass $coupon) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [new Address($this->order->customer_email, trim($this->order->customer_first_name.' '.$this->order->customer_last_name))],
            subject: '¡Tienes un cupón de '.$this->coupon->percent.'% de descuento!',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'examen::emails.auto-coupon');
    }
}
