<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExamenSetup extends Command
{
    protected $signature = 'examen:setup {--seed : Tambien carga las categorias y productos de Café Catracho}';

    protected $description = 'Configura la tienda: PayPal Sandbox, correos de registro, moneda HNL y datos de la tienda';

    public function handle(): int
    {
        $this->configurePayPal();
        $this->configureEmails();
        $this->configureCurrency();
        $this->configureStoreIdentity();

        if ($this->option('seed')) {
            $this->call('db:seed', ['--class' => 'Database\\Seeders\\CafeStoreSeeder', '--force' => true]);
            $this->call('indexer:index', ['--mode' => ['full']]);
        }

        $this->call('cache:clear');
        $this->info('Listo. Revisa el .env para WhatsApp, Tawk.to y las credenciales de PayPal Sandbox.');

        return self::SUCCESS;
    }

    protected function configurePayPal(): void
    {
        $paypal = config('examen.paypal');

        if (! $paypal['client_id'] || ! $paypal['client_secret']) {
            $this->warn('PayPal: no hay PAYPAL_SANDBOX_CLIENT_ID / PAYPAL_SANDBOX_CLIENT_SECRET en el .env; se conservan las credenciales guardadas en el admin.');
        }

        $prefix = 'sales.payment_methods.paypal_smart_button.';

        $this->setConfig($prefix.'active', '1');
        $this->setConfig($prefix.'sandbox', '1');
        $this->setConfig($prefix.'title', 'PayPal');
        $this->setConfig($prefix.'description', 'Paga con tu cuenta PayPal o tarjeta (Sandbox).');
        // Las credenciales solo se escriben si estan en el .env; asi no se borran las que ya
        // se guardaron desde el admin (Configuracion > Ventas > Metodos de pago).
        if ($paypal['client_id'] && $paypal['client_secret']) {
            $this->setConfig($prefix.'client_id', (string) $paypal['client_id']);
            $this->setConfig($prefix.'client_secret', (string) $paypal['client_secret']);
        }
        $this->setConfig($prefix.'accepted_currencies', $paypal['accepted_currencies']);
        $this->setConfig($prefix.'sort', '1');

        // PayPal Standard queda apagado para que solo aparezca un boton de PayPal.
        $this->setConfig('sales.payment_methods.paypal_standard.active', '0');

        $this->info('PayPal Smart Button activado en modo Sandbox.');
    }

    protected function configureEmails(): void
    {
        $base = 'emails.general.notifications.emails.general.notifications.';

        // Correo de bienvenida al cliente y aviso al administrador cuando alguien se registra.
        $this->setConfig($base.'registration', '1');
        $this->setConfig($base.'customer_registration_confirmation_mail_to_admin', '1');

        $admin = [
            'name' => env('ADMIN_MAIL_NAME', config('mail.from.name')),
            'email' => env('ADMIN_MAIL_ADDRESS', config('mail.from.address')),
        ];

        $this->setConfig('emails.configure.email_settings.admin_name', (string) $admin['name']);
        $this->setConfig('emails.configure.email_settings.admin_email', (string) $admin['email']);
        $this->setConfig('emails.configure.email_settings.sender_name', (string) config('mail.from.name'));
        $this->setConfig('emails.configure.email_settings.shop_email_from', (string) config('mail.from.address'));

        $this->info("Correos de registro activados (cliente y administrador: {$admin['email']}).");
    }

    protected function configureCurrency(): void
    {
        $base = DB::table('currencies')->where('code', config('app.currency', 'USD'))->first();

        // Si la instalacion no incluyo HNL como moneda permitida, se crea aqui.
        if (! DB::table('currencies')->where('code', 'HNL')->exists()) {
            DB::table('currencies')->insert([
                'code' => 'HNL',
                'name' => 'Lempira hondureño',
                'symbol' => 'L',
                'decimal' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->info('Moneda HNL creada.');
        }

        $hnl = DB::table('currencies')->where('code', 'HNL')->first();

        $channel = DB::table('channels')->first();

        if ($channel) {
            DB::table('channel_currencies')->updateOrInsert(
                ['channel_id' => $channel->id, 'currency_id' => $hnl->id]
            );
        }

        DB::table('currency_exchange_rates')->updateOrInsert(
            ['target_currency' => $hnl->id],
            ['rate' => config('examen.paypal.hnl_rate'), 'created_at' => now(), 'updated_at' => now()]
        );

        $this->info('Moneda HNL habilitada: 1 '.($base->code ?? 'USD').' = '.config('examen.paypal.hnl_rate').' HNL.');
    }

    protected function configureStoreIdentity(): void
    {
        $name = config('examen.store_name');
        $channel = DB::table('channels')->first();

        if (! $channel) {
            return;
        }

        DB::table('channel_translations')->where('channel_id', $channel->id)->update([
            'name' => $name,
            'home_seo' => json_encode([
                'meta_title' => $name.' | Café hondureño de altura',
                'meta_keywords' => 'café, Honduras, Marcala, Copán, Opalaca',
                'meta_description' => 'Tienda en línea de café hondureño de altura: grano, molido y accesorios.',
            ]),
        ]);

        $this->info("Nombre de la tienda: {$name}.");
    }

    /**
     * Guarda un valor en core_config respetando si el campo es por canal / idioma.
     */
    protected function setConfig(string $code, string $value): void
    {
        $field = system_config()->getConfigField($code) ?? [];

        $channel = ! empty($field['channel_based']) ? core()->getDefaultChannelCode() : null;
        $locale = ! empty($field['locale_based']) ? core()->getDefaultChannel()->default_locale->code : null;

        DB::table('core_config')->updateOrInsert(
            ['code' => $code, 'channel_code' => $channel, 'locale_code' => $locale],
            ['value' => $value, 'created_at' => now(), 'updated_at' => now()]
        );
    }
}
