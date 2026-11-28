<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


class Item extends Model {
    use HasFactory;

    protected $fillable = [
        'name','sku','item_type','category_id',
        'unit','unit_cost','description','notes','is_active','created_by'
    ];

    protected $casts = ['is_active' => 'boolean', 'unit_cost' => 'decimal:2'];

    // Relaciones
    public function category()    { return $this->belongsTo(Category::class); }
    public function stocks()      { return $this->hasMany(InventoryStock::class); }
    public function movements()   { return $this->hasMany(InventoryMovement::class); }
    public function creator()     { return $this->belongsTo(User::class, 'created_by'); }

    // Stock total disponible
    public function getTotalStockAttribute(): float {
        return $this->stocks->sum('quantity') - $this->stocks->sum('reserved_qty');
    }

    // ¿Stock bajo? Se evalúa por artículo: la existencia es la suma de todas sus ubicaciones y el
    // mínimo es la suma de los mínimos de esas ubicaciones. Un artículo sin existencias registradas
    // no genera alerta.
    public function getIsLowStockAttribute(): bool {
        if ($this->stocks->isEmpty()) return false;
        return round((float) $this->stocks->sum('quantity'), 4) <= round((float) $this->stocks->sum('min_quantity'), 4);
    }

    // Scopes
    public function scopeActive($q)           { return $q->where('is_active', true); }
    public function scopeByType($q, $type)    { return $q->where('item_type', $type); }
    public function scopeLowStock($q) {
        return $q->whereHas('stocks')->whereRaw(
            '(select coalesce(sum(quantity), 0) from inventory_stocks where inventory_stocks.item_id = items.id)'
            . ' <= (select coalesce(sum(min_quantity), 0) from inventory_stocks where inventory_stocks.item_id = items.id)'
        );
    }
}