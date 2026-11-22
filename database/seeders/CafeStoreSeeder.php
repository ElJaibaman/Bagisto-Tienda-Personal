<?php

namespace Database\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Webkul\Category\Models\Category;
use Webkul\Product\Repositories\ProductRepository;

/**
 * Catalogo de "Café Catracho" (tienda de café hondureño).
 * Precios definidos en Lempiras y guardados en la moneda base usando HNL_EXCHANGE_RATE.
 * Es idempotente: si el SKU ya existe no lo vuelve a crear.
 */
class CafeStoreSeeder extends Seeder
{
    public function run(): void
    {
        // db:seed corre en modo "unguarded"; los repositorios de Bagisto dependen del $fillable.
        Model::reguard();

        $categories = $this->seedCategories();

        $rate = (float) config('examen.paypal.hnl_rate', 26.5);
        $repo = app(ProductRepository::class);
        $inventorySource = DB::table('inventory_sources')->value('id');
        $assets = database_path('seeders/assets');

        foreach ($this->products() as $item) {
            if ($repo->findOneByField('sku', $item['sku'])) {
                $this->command?->line("Producto {$item['sku']} ya existe, se omite.");

                continue;
            }

            $product = $repo->create([
                'type' => 'simple',
                'attribute_family_id' => 1,
                'sku' => $item['sku'],
            ]);

            $repo->update([
                'sku' => $item['sku'],
                'name' => $item['name'],
                'url_key' => $item['sku'],
                'short_description' => '<p>'.$item['short'].'</p>',
                'description' => '<p>'.$item['description'].'</p>',
                'price' => round($item['hnl'] / $rate, 4),
                'weight' => $item['weight'],
                'new' => 1,
                'featured' => $item['featured'] ? 1 : 0,
                'visible_individually' => 1,
                'status' => 1,
                'manage_stock' => 1,
                'meta_title' => $item['name'],
                'meta_keywords' => 'café, Honduras',
                'meta_description' => $item['short'],
                'channel' => core()->getDefaultChannelCode(),
                'locale' => core()->getDefaultChannel()->default_locale->code,
                'channels' => [core()->getDefaultChannel()->id],
                'categories' => [$categories[$item['category']]],
                'inventories' => [$inventorySource => $item['stock']],
                'images' => ['files' => [
                    new UploadedFile($assets.'/'.$item['image'].'.jpg', $item['image'].'.jpg', 'image/jpeg', null, true),
                ]],
            ], $product->id);

            $this->command?->info("Producto creado: {$item['name']}");
        }
    }

    protected function seedCategories(): array
    {
        $definitions = [
            'cafe-en-grano' => ['Café en grano', 'Whole bean coffee', 'Café de altura en grano, tostado en Honduras.'],
            'cafe-molido' => ['Café molido', 'Ground coffee', 'Listo para tu cafetera, molido al momento del empaque.'],
            'accesorios' => ['Accesorios y regalos', 'Accessories & gifts', 'Todo para preparar y regalar buen café.'],
        ];

        $ids = [];
        $position = 1;

        foreach ($definitions as $slug => [$es, $en, $description]) {
            $existing = DB::table('category_translations')->where('slug', $slug)->value('category_id');

            if ($existing) {
                $ids[$slug] = $existing;

                continue;
            }

            // Se crea el nodo y luego las traducciones directo en la tabla, porque la
            // configuracion de Translatable de este proyecto solo reconoce "es-MX"/"es-CO", no "es".
            $category = Category::create([
                'parent_id' => core()->getDefaultChannel()->root_category_id,
                'position' => $position++,
                'status' => 1,
                'display_mode' => 'products_and_description',
            ]);

            foreach (['es' => [$es, 'café'], 'en' => [$en, 'coffee']] as $localeCode => [$name, $keywords]) {
                DB::table('category_translations')->insert([
                    'category_id' => $category->id,
                    'locale' => $localeCode,
                    'locale_id' => DB::table('locales')->where('code', $localeCode)->value('id'),
                    'name' => $name,
                    'slug' => $slug,
                    'url_path' => '',
                    'description' => $description,
                    'meta_title' => $name,
                    'meta_description' => $description,
                    'meta_keywords' => $keywords,
                ]);
            }

            $ids[$slug] = $category->id;
        }

        return $ids;
    }

    protected function products(): array
    {
        return [
            ['sku' => 'cafe-marcala-grano', 'name' => 'Café Marcala en grano 1 lb', 'category' => 'cafe-en-grano', 'hnl' => 280, 'weight' => 0.45, 'stock' => 120, 'featured' => true, 'image' => 'marcala',
                'short' => 'Café de altura de Marcala, notas a caramelo y cítricos.',
                'description' => 'Café 100% arábica cultivado sobre los 1,400 msnm en Marcala, La Paz. Tueste medio, cuerpo balanceado, notas a caramelo, panela y un toque cítrico.'],
            ['sku' => 'cafe-copan-grano', 'name' => 'Café Copán en grano 1 lb', 'category' => 'cafe-en-grano', 'hnl' => 265, 'weight' => 0.45, 'stock' => 100, 'featured' => true, 'image' => 'copan',
                'short' => 'Dulce y suave, de las montañas de Copán.',
                'description' => 'Café de las montañas de Copán con acidez brillante y dulzor a chocolate con leche. Ideal para espresso y métodos de filtrado.'],
            ['sku' => 'cafe-pico-bonito-organico', 'name' => 'Café Orgánico Pico Bonito 1 lb', 'category' => 'cafe-en-grano', 'hnl' => 330, 'weight' => 0.45, 'stock' => 60, 'featured' => false, 'image' => 'pico-bonito',
                'short' => 'Orgánico certificado cultivado cerca del Parque Nacional Pico Bonito.',
                'description' => 'Café orgánico de sombra cultivado en las faldas de Pico Bonito, La Ceiba. Perfil limpio, floral y con final largo a frutos rojos.'],
            ['sku' => 'cafe-opalaca-molido', 'name' => 'Café Opalaca molido 1 lb', 'category' => 'cafe-molido', 'hnl' => 250, 'weight' => 0.45, 'stock' => 150, 'featured' => true, 'image' => 'opalaca',
                'short' => 'Molido medio para cafetera de goteo.',
                'description' => 'Café de Opalaca molido medio, listo para cafetera de goteo. Notas a nuez y cacao con cuerpo cremoso.'],
            ['sku' => 'cafe-montecillos-molido', 'name' => 'Café Montecillos molido 1 lb', 'category' => 'cafe-molido', 'hnl' => 255, 'weight' => 0.45, 'stock' => 140, 'featured' => false, 'image' => 'montecillos',
                'short' => 'Tueste oscuro, el favorito para el café de la mañana.',
                'description' => 'Café de las montañas de Montecillos con tueste oscuro, aroma intenso y sabor achocolatado. Perfecto para el café de la mañana.'],
            ['sku' => 'taza-lenca-artesanal', 'name' => 'Taza de barro Lenca artesanal', 'category' => 'accesorios', 'hnl' => 180, 'weight' => 0.4, 'stock' => 40, 'featured' => false, 'image' => 'taza',
                'short' => 'Hecha a mano por artesanas de Lepaera.',
                'description' => 'Taza de barro de 300 ml elaborada a mano con técnicas lencas tradicionales. Cada pieza es única.'],
            ['sku' => 'prensa-francesa-600', 'name' => 'Prensa francesa 600 ml', 'category' => 'accesorios', 'hnl' => 520, 'weight' => 0.9, 'stock' => 25, 'featured' => false, 'image' => 'prensa',
                'short' => 'Vidrio borosilicato y filtro de acero inoxidable.',
                'description' => 'Prensa francesa de 600 ml con vidrio borosilicato resistente al calor y filtro doble de acero inoxidable.'],
            ['sku' => 'kit-degustacion-3-cafes', 'name' => 'Kit degustación 3 cafés de origen', 'category' => 'accesorios', 'hnl' => 450, 'weight' => 0.5, 'stock' => 50, 'featured' => true, 'image' => 'kit',
                'short' => 'Marcala, Copán y Opalaca en bolsas de 4 oz.',
                'description' => 'El regalo ideal: tres bolsas de 4 oz con los orígenes más queridos de Honduras, en una caja de cartón reciclado.'],
        ];
    }
}
