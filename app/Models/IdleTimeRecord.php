<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class IdleTimeRecord extends Model {
    public $timestamps = false;
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;
    protected $fillable = ['work_order_id','cause','duration_min','worker_name','economic_cost','notes','recorded_by'];

    public function workOrder()  { return $this->belongsTo(WorkOrder::class); }
    public function recordedBy() { return $this->belongsTo(User::class, 'recorded_by'); }
}