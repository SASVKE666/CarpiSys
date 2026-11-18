<?php
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Supplier extends Model {
    protected $fillable = ['name','contact','phone','email','address','notes','is_active'];
    public function purchaseOrders() { return $this->hasMany(PurchaseOrder::class); }
}
