<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model {
    protected $fillable = [
        'code','supplier_id','status','ordered_date',
        'expected_date','received_date','subtotal','tax','total','notes','created_by'
    ];
    protected $casts = [
        'ordered_date'  => 'date',
        'expected_date' => 'date',
        'received_date' => 'date',
    ];

    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function items()    { return $this->hasMany(PurchaseOrderItem::class); }
    public function creator()  { return $this->belongsTo(User::class, 'created_by'); }
}