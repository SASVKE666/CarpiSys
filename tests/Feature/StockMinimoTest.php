<?php

namespace Tests\Feature;

use App\Models\InventoryStock;
use App\Models\Item;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La alerta de stock mínimo es por artículo: existencia = suma de todas las ubicaciones,
 * mínimo = suma de los mínimos de esas ubicaciones.
 */
class StockMinimoTest extends TestCase
{
    use RefreshDatabase;

    private Location $principal;
    private Location $secundario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->principal  = Location::create(['name' => 'Principal', 'is_active' => true]);
        $this->secundario = Location::create(['name' => 'Secundario', 'is_active' => true]);
    }

    private function articulo(string $sku, float $principal, float $minPrincipal, ?float $secundario = null): Item
    {
        $item = Item::create(['name' => "Art $sku", 'sku' => $sku, 'item_type' => 'materia_prima', 'unit' => 'pieza', 'unit_cost' => 10]);
        InventoryStock::create(['item_id' => $item->id, 'location_id' => $this->principal->id,
            'quantity' => $principal, 'min_quantity' => $minPrincipal, 'avg_cost' => 10]);
        if ($secundario !== null) {
            InventoryStock::create(['item_id' => $item->id, 'location_id' => $this->secundario->id,
                'quantity' => $secundario, 'min_quantity' => 0, 'avg_cost' => 10]);
        }
        return $item->fresh('stocks');
    }

    public function test_un_almacen_bajo_no_dispara_alerta_si_el_total_alcanza(): void
    {
        // Principal 5 (mínimo 10) pero Secundario tiene 30: total 35 > 10.
        $item = $this->articulo('A-1', 5, 10, 30);

        $this->assertFalse($item->is_low_stock);
        $this->assertSame(0, Item::lowStock()->count());
    }

    public function test_alerta_cuando_el_total_llega_al_minimo(): void
    {
        // Total 10 = mínimo 10: igual que antes, igualar el mínimo ya es alerta.
        $item = $this->articulo('A-2', 4, 10, 6);

        $this->assertTrue($item->is_low_stock);
        $this->assertSame(1, Item::lowStock()->count());
    }

    public function test_alerta_cuando_el_total_esta_por_debajo(): void
    {
        $item = $this->articulo('A-3', 2, 10, 3);

        $this->assertTrue($item->is_low_stock);
        $this->assertSame([$item->id], Item::lowStock()->pluck('id')->all());
    }

    public function test_articulo_sin_existencias_registradas_no_genera_alerta(): void
    {
        $item = Item::create(['name' => 'Sin stock', 'sku' => 'A-4', 'item_type' => 'materia_prima', 'unit' => 'pieza', 'unit_cost' => 10]);

        $this->assertFalse($item->fresh('stocks')->is_low_stock);
        $this->assertSame(0, Item::lowStock()->count());
    }

    public function test_el_scope_y_el_atributo_coinciden(): void
    {
        $this->articulo('B-1', 5, 10, 30);   // total 35, mínimo 10: bien
        $this->articulo('B-2', 4, 10, 6);    // total 10, mínimo 10: alerta
        $this->articulo('B-3', 50, 10);      // total 50, mínimo 10: bien
        $this->articulo('B-4', 0, 5);        // total 0, mínimo 5: alerta

        $porAtributo = Item::with('stocks')->get()->filter->is_low_stock->pluck('sku')->sort()->values()->all();
        $porScope    = Item::lowStock()->pluck('sku')->sort()->values()->all();

        $this->assertSame(['B-2', 'B-4'], $porAtributo);
        $this->assertSame($porAtributo, $porScope);
    }

    public function test_el_estado_de_cada_ubicacion_usa_el_total_del_articulo(): void
    {
        $item = $this->articulo('C-1', 5, 10, 30);

        foreach (InventoryStock::with('item.stocks')->where('item_id', $item->id)->get() as $stock) {
            $this->assertFalse($stock->is_low);
        }
    }
}
