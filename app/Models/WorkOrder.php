<?php 
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkOrder extends Model {
    use HasFactory;

    protected $fillable = [
        'code','client_name','client_contact','description','status',
        'start_date','due_date','completed_date',
        'materials_cost','labor_cost','overhead_cost','quoted_price','notes','created_by'
    ];
    protected $casts = [
        'start_date'     => 'date',
        'due_date'       => 'date',
        'completed_date' => 'date',
        'materials_cost' => 'decimal:2',
        'labor_cost'     => 'decimal:2',
        'overhead_cost'  => 'decimal:2',
        'quoted_price'   => 'decimal:2',
    ];

    public function materials()    { return $this->hasMany(WorkOrderMaterial::class); }
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

    // Recalcula costos desde los registros hijo
    public function recalculateCosts(): void {
        $this->materials_cost = $this->materials->sum(fn($m) => $m->qty_actual * $m->unit_cost);
        $this->labor_cost     = $this->laborRecords->sum(fn($l) => $l->hours * $l->hourly_rate);
        $this->save();
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