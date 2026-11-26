<?php 
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class WorkOrder extends Model {
    use HasFactory;

    protected $fillable = [
        'code','client_name','client_contact','description','status',
        'start_date','due_date','completed_date','machine_hours',
        'materials_cost','labor_cost','overhead_cost','quoted_price','notes','created_by'
    ];
    protected $casts = [
        'start_date'     => 'date',
        'due_date'       => 'date',
        'completed_date' => 'date',
        'machine_hours'  => 'decimal:2',
        'materials_cost' => 'decimal:2',
        'labor_cost'     => 'decimal:2',
        'overhead_cost'  => 'decimal:2',
        'quoted_price'   => 'decimal:2',
    ];

    public function materials()    { return $this->hasMany(WorkOrderMaterial::class); }
    public function estimates()    { return $this->hasMany(WorkOrderEstimate::class); }
    public function laborRecords() { return $this->hasMany(LaborRecord::class); }
    public function idleTimes()    { return $this->hasMany(IdleTimeRecord::class); }
    public function creator()      { return $this->belongsTo(User::class, 'created_by'); }

    // Atributos calculados
    public function getTotalCostAttribute(): float {
        return $this->materials_cost + $this->labor_cost + $this->overhead_cost;
    }
    public function getProfitAttribute(): float {
        return $this->quoted_price - $this->total_cost;
    }
    public function getMarginPctAttribute(): float {
        return $this->quoted_price > 0
            ? round($this->profit / $this->quoted_price * 100, 2)
            : 0;
    }

    /** Estados en los que todavía se pueden registrar materiales y horas. */
    public const OPEN_STATUSES = ['pendiente', 'en_proceso', 'pausada'];

    public function isOpen(): bool {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    /**
     * Una orden se costea automáticamente en cuanto tiene materiales u horas registrados.
     * Antes de eso, sus costos son los estimados que escribió el administrador.
     */
    public function usesAutoCosting(): bool {
        return $this->materials()->exists() || $this->laborRecords()->exists();
    }

    /**
     * Recalcula MD + MOD + CIF desde los registros:
     *  - MD  = suma de (cantidad usada × costo unitario al momento de usarla)
     *  - MOD = suma de (horas × tarifa por hora)
     *  - CIF = suma de las tasas activas aplicadas a esta orden (ver calculateOverhead)
     */
    public function recalculateCosts(): void {
        $materials = (float) $this->materials()->sum(DB::raw('qty_actual * unit_cost'));
        $labor     = (float) $this->laborRecords()->sum(DB::raw('hours * hourly_rate'));

        $this->materials_cost = round($materials, 2);
        $this->labor_cost     = round($labor, 2);
        $this->overhead_cost  = $this->calculateOverhead($materials, $labor);
        $this->save();
    }

    /**
     * Costos indirectos de fabricación según las tasas activas:
     *  - porcentaje_mano_obra  : % de la mano de obra de la orden
     *  - porcentaje_materiales : % de los materiales de la orden
     *  - por_hora_maquina      : tarifa × horas de máquina de la orden
     * El tipo "monto_fijo_mensual" no se reparte automáticamente (no hay base de reparto definida).
     */
    public function calculateOverhead(float $materials, float $labor): float {
        $total = 0.0;
        foreach (OverheadRate::active()->get() as $rate) {
            $v = (float) $rate->value;
            $total += match ($rate->rate_type) {
                'porcentaje_mano_obra'  => $labor * $v / 100,
                'porcentaje_materiales' => $materials * $v / 100,
                'por_hora_maquina'      => (float) $this->machine_hours * $v,
                default                 => 0.0,
            };
        }
        return round($total, 2);
    }

    // Genera código OT automático
    protected static function booted(): void {
        static::creating(function (self $ot) {
            if (empty($ot->code)) {
                $last = static::max('id') ?? 0;
                $ot->code = 'OT-' . str_pad($last + 1, 4, '0', STR_PAD_LEFT);
            }
        });
        static::updated(function (self $ot) {
            if ($ot->wasChanged('status') && $ot->status === 'completada') {
                $ot->completed_date = now()->toDateString();
                $ot->saveQuietly();
            }
        });
    }

    public function scopeByStatus($q, $status) { return $q->where('status', $status); }
    public function scopeActive($q) { return $q->whereIn('status', ['pendiente','en_proceso']); }
}