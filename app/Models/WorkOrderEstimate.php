<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Cantidad de un artículo que se calcula que necesita una orden de trabajo. */
class WorkOrderEstimate extends Model
{
    protected $fillable = ['work_order_id', 'item_id', 'quantity'];
    protected $casts    = ['quantity' => 'decimal:4'];

    public function workOrder() { return $this->belongsTo(WorkOrder::class); }
    public function item()      { return $this->belongsTo(Item::class); }
}
