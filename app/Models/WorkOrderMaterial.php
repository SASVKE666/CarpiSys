<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderMaterial extends Model {
    protected $fillable = ['work_order_id','item_id','location_id','qty_estimated','qty_actual','unit_cost','notes'];
    protected $casts    = ['qty_estimated'=>'decimal:4','qty_actual'=>'decimal:4','unit_cost'=>'decimal:2'];

    public function workOrder() { return $this->belongsTo(WorkOrder::class); }
    public function item()      { return $this->belongsTo(Item::class); }
    public function location()  { return $this->belongsTo(Location::class); }

    public function getTotalCostAttribute(): float { return $this->qty_actual * $this->unit_cost; }
}
