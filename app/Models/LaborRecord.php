<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaborRecord extends Model {
    public $timestamps = false;
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;
    protected $fillable = ['work_order_id','worker_name','work_date','hours','hourly_rate','task_description','notes'];
    protected $casts    = ['work_date'=>'date','hours'=>'decimal:2','hourly_rate'=>'decimal:2'];

    public function workOrder() { return $this->belongsTo(WorkOrder::class); }
    public function getTotalAttribute(): float { return $this->hours * $this->hourly_rate; }
}