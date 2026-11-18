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

    // ¿Alguna ubicación con stock bajo?
    public function getIsLowStockAttribute(): bool {
        return $this->stocks->contains(fn($s) => $s->quantity <= $s->min_quantity);
    }

    // Scopes
    public function scopeActive($q)           { return $q->where('is_active', true); }
    public function scopeByType($q, $type)    { return $q->where('item_type', $type); }
    public function scopeLowStock($q) {
        return $q->whereHas('stocks', fn($s) =>
            $s->whereColumn('quantity', '<=', 'min_quantity')
        );
    }
}