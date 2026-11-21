<?php

namespace App\Http\Controllers;

use App\Models\OverheadRate;
use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Tasas de Costos Indirectos de Fabricación (CIF). Solo el administrador (ver routes/web.php).
 */
class OverheadRateController extends Controller
{
    /** Tipos que el sistema reparte automáticamente entre las órdenes (ver WorkOrder::calculateOverhead). */
    public const TYPES = ['porcentaje_mano_obra', 'porcentaje_materiales', 'por_hora_maquina'];

    public function index()
    {
        return Inertia::render('OverheadRates/Index', [
            'rates' => OverheadRate::orderBy('name')->get(['id', 'name', 'rate_type', 'value', 'description', 'is_active']),
            'types' => self::TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, creating: true);
        OverheadRate::create($data + ['is_active' => $data['is_active'] ?? true]);
        $this->recalculateOpenOrders();

        return redirect()->route('overhead-rates.index')->with('success', 'Tasa creada. Las órdenes abiertas se recalcularon.');
    }

    public function update(Request $request, OverheadRate $overheadRate)
    {
        $overheadRate->update($this->validated($request, creating: false));
        $this->recalculateOpenOrders();

        return redirect()->route('overhead-rates.index')->with('success', 'Tasa actualizada. Las órdenes abiertas se recalcularon.');
    }

    private function validated(Request $request, bool $creating): array
    {
        $sometimes = $creating ? 'required' : 'sometimes|required';

        $data = $request->validate([
            'name'        => "$sometimes|string|max:120",
            'rate_type'   => [$creating ? 'required' : 'sometimes', Rule::in(self::TYPES)],
            'value'       => "$sometimes|numeric|min:0|max:100000",
            'description' => 'nullable|string|max:500',
            'is_active'   => 'sometimes|boolean',
        ]);

        // Un porcentaje por encima de 500 casi seguro es un error de captura.
        $type = $data['rate_type'] ?? $request->route('overheadRate')?->rate_type;
        if (isset($data['value']) && str_starts_with((string) $type, 'porcentaje') && $data['value'] > 500) {
            throw ValidationException::withMessages(['value' => 'Un porcentaje no puede ser mayor a 500.']);
        }

        return $data;
    }

    /**
     * Cambiar una tasa modifica el CIF de las órdenes que siguen abiertas y se costean con registros.
     * Las órdenes completadas o canceladas conservan sus costos.
     */
    private function recalculateOpenOrders(): void
    {
        WorkOrder::whereIn('status', WorkOrder::OPEN_STATUSES)->get()
            ->filter(fn (WorkOrder $o) => $o->usesAutoCosting())
            ->each->recalculateCosts();
    }
}
