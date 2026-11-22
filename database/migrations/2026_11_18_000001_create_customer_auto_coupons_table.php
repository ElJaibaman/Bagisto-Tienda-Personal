<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_auto_coupons', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('customer_id')->unsigned()->index();
            $table->integer('cart_rule_id')->unsigned();
            $table->integer('order_id')->unsigned()->nullable();
            $table->string('code')->unique();
            $table->unsignedInteger('milestone');
            $table->decimal('accumulated_total', 14, 4)->default(0);
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('cart_rule_id')->references('id')->on('cart_rules')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_auto_coupons');
    }
};
