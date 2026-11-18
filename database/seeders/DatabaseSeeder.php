<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();
        $now = Carbon::now();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        
        DB::table('suppliers')->insert([
            ['name' => 'Maderas y Triplay de Nayarit', 'contact' => 'Carlos Robles', 'phone' => '3111234567', 'email' => 'ventas@maderasnayarit.com', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Herrajes Finos Tepic', 'contact' => 'Ana Gómez', 'phone' => '3119876543', 'email' => 'ana@herrajestepic.com', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('categories')->insert([
            ['name' => 'Tableros'],
            ['name' => 'Madera Sólida'],
            ['name' => 'Herrajes'],
            ['name' => 'Maquinaria'],
            ['name' => 'Acabados'],
            ['name' => 'Muebles'],
            ['name' => 'Fijaciones'],
            ['name' => 'Herramienta Menor'],
            ['name' => 'Pinturas'],
            ['name' => 'Consumibles Varios'],
        ]);
        DB::table('locations')->insert([
            ['name' => 'Almacén Principal (Bodega)', 'description' => 'Área de almacenamiento de tableros y maderas', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Taller de Ensamblaje', 'description' => 'Área de armado y herramienta en piso', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('items')->insert([
            [
                'name' => 'Tablero Melamina Blanca 15mm (1.22x2.44m)', 'sku' => 'MAT-001', 'item_type' => 'materia_prima', 
                'category_id' => 1, 'unit' => 'pieza', 'unit_cost' => 650.00, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now
            ],
            [
                'name' => 'Bisagra Bidimensional Cierre Lento', 'sku' => 'MAT-002', 'item_type' => 'materia_prima', 
                'category_id' => 2, 'unit' => 'pieza', 'unit_cost' => 25.50, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now
            ],
            [
                'name' => 'Pegamento Blanco Profesional 1 Galón', 'sku' => 'CON-001', 'item_type' => 'consumible', 
                'category_id' => 3, 'unit' => 'galon', 'unit_cost' => 280.00, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now
            ],
            [
                'name' => 'Sierra Circular Inalámbrica Makita', 'sku' => 'HER-001', 'item_type' => 'herramienta', 
                'category_id' => 4, 'unit' => 'pieza', 'unit_cost' => 3500.00, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now
            ],
        ]);
        DB::table('inventory_stocks')->insert([
            ['item_id' => 1, 'location_id' => 1, 'quantity' => 15, 'reserved_qty' => 5, 'min_quantity' => 10, 'avg_cost' => 650.00, 'updated_at' => $now],
            ['item_id' => 2, 'location_id' => 2, 'quantity' => 12, 'reserved_qty' => 0, 'min_quantity' => 20, 'avg_cost' => 25.50, 'updated_at' => $now], // Stock bajo
            ['item_id' => 3, 'location_id' => 2, 'quantity' => 3,  'reserved_qty' => 0, 'min_quantity' => 2,  'avg_cost' => 280.00, 'updated_at' => $now],
            ['item_id' => 4, 'location_id' => 2, 'quantity' => 2,  'reserved_qty' => 0, 'min_quantity' => 1,  'avg_cost' => 3500.00,'updated_at' => $now],
        ]);
        DB::table('work_orders')->insert([
            [
                'code' => 'OT-0001',
                'client_name' => 'Familia Martínez',
                'client_contact' => '311-555-1234',
                'description' => 'Cocina Integral MDF Enchapado',
                'status' => 'completada',
                'start_date' => $now->copy()->subDays(15),
                'due_date' => $now->copy()->subDays(2),
                'completed_date' => $now->copy()->subDays(2),
                'materials_cost' => 8500.00,
                'labor_cost' => 3500.00,
                'overhead_cost' => 1200.00,
                'quoted_price' => 18500.00,
                'created_at' => $now, 'updated_at' => $now
            ],
            [
                'code' => 'OT-0002',
                'client_name' => 'Oficinas Tepic Centro',
                'client_contact' => '311-987-6543',
                'description' => 'Mueble de Recepción Melamina',
                'status' => 'en_proceso',
                'start_date' => $now->copy()->subDays(5),
                'due_date' => $now->copy()->addDays(5),
                'completed_date' => null,
                'materials_cost' => 3200.00,
                'labor_cost' => 1500.00,
                'overhead_cost' => 500.00,
                'quoted_price' => 5500.00, // Caso de margen bajo/rojo
                'created_at' => $now, 'updated_at' => $now
            ],
            [
                'code' => 'OT-0003',
                'client_name' => 'Roberto Sánchez',
                'client_contact' => 'roberto@email.com',
                'description' => 'Clóset Recámara Principal',
                'status' => 'pendiente',
                'start_date' => $now->copy()->addDays(1),
                'due_date' => $now->copy()->addDays(10),
                'completed_date' => null,
                'materials_cost' => 4100.00,
                'labor_cost' => 2000.00,
                'overhead_cost' => 600.00,
                'quoted_price' => 7800.00,
                'created_at' => $now, 'updated_at' => $now
            ]
        ]);

    }
}
