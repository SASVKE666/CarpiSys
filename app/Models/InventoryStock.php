<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class InventoryStock extends Model {
    public $timestamps = false;
    protected $fillable = ['item_id','location_id','quantity','reserved_qty','min_quantity','avg_cost'];
    protected $casts = ['quantity'=>'decimal:4','reserved_qty'=>'decimal:4','min_quantity'=>'decimal:4'];

    public function item()     { return $this->belongsTo(Item::class); }
    public function location() { return $this->belongsTo(Location::class); }

    public function getAvailableAttribute(): float {
        return max(0, $this->quantity - $this->reserved_qty);
    }
    public function getIsLowAttribute(): bool {
        return $this->quantity <= $this->min_quantity;
    }
}