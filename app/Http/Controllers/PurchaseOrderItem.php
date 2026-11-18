<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model {
    public $timestamps = false;

    protected $fillable = [
        'purchase_order_id',
        'item_id',
        'quantity_ordered',
        'quantity_received',
        'unit_cost',
    ];

    protected $casts = [
        'quantity_ordered'  => 'decimal:4',
        'quantity_received' => 'decimal:4',
        'unit_cost'         => 'decimal:2',
    ];

    public function purchaseOrder() { return $this->belongsTo(PurchaseOrder::class); }
    public function item()          { return $this->belongsTo(Item::class); }

    public function getSubtotalAttribute(): float {
        return $this->quantity_ordered * $this->unit_cost;
    }
}