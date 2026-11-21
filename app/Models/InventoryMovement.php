<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class InventoryMovement extends Model {
    public $timestamps = false;
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'item_id','location_id','movement_type','quantity',
        'unit_cost','reference_type','reference_id','notes','user_id'
    ];
    protected $casts = ['quantity'=>'decimal:4','unit_cost'=>'decimal:2','created_at'=>'datetime'];

    public function item()     { return $this->belongsTo(Item::class); }
    public function location() { return $this->belongsTo(Location::class); }
    public function user()     { return $this->belongsTo(User::class); }

    // Actualiza el stock automáticamente al crear un movimiento
    protected static function booted(): void {
        // $timestamps está desactivado, así que la fecha del movimiento se asigna aquí.
        static::creating(fn (self $m) => $m->created_at ??= now());

        static::created(function (self $movement) {
            $stock = InventoryStock::firstOrCreate(
                ['item_id' => $movement->item_id, 'location_id' => $movement->location_id],
                ['quantity' => 0, 'reserved_qty' => 0, 'min_quantity' => 0, 'avg_cost' => 0]
            );
            $delta = in_array($movement->movement_type, ['entrada','devolucion'])
                ? $movement->quantity
                : -$movement->quantity;
            $stock->increment('quantity', $delta);
            $stock->touch();
        });
    }
}