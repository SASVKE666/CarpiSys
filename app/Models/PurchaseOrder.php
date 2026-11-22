<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model {
    protected $fillable = [
        'code','supplier_id','status','ordered_date',
        'expected_date','received_date','subtotal','tax_rate','tax','total','notes','created_by'
    ];
    protected $casts = [
        'ordered_date'  => 'date',
        'expected_date' => 'date',
        'received_date' => 'date',
        'tax_rate'      => 'decimal:2',
    ];

    public const STATUSES = ['borrador', 'enviada', 'parcial', 'recibida', 'cancelada'];

    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function items()    { return $this->hasMany(PurchaseOrderItem::class); }
    public function creator()  { return $this->belongsTo(User::class, 'created_by'); }

    public function isDraft(): bool      { return $this->status === 'borrador'; }
    /** Todavía llega mercancía: enviada al proveedor o recibida solo en parte. */
    public function canReceive(): bool   { return in_array($this->status, ['enviada', 'parcial'], true); }

    /** subtotal = Σ cantidad pedida × costo; IVA = subtotal × tasa; total = subtotal + IVA. */
    public function recalculateTotals(): void {
        $subtotal = (float) $this->items()->get()
            ->sum(fn ($l) => (float) $l->quantity_ordered * (float) $l->unit_cost);
        $this->subtotal = round($subtotal, 2);
        $this->tax      = round($subtotal * (float) $this->tax_rate / 100, 2);
        $this->total    = round($this->subtotal + $this->tax, 2);
        $this->save();
    }

    // Código automático OC-0001, OC-0002...
    protected static function booted(): void {
        static::creating(function (self $po) {
            if (empty($po->code)) {
                $last = static::max('id') ?? 0;
                $po->code = 'OC-' . str_pad($last + 1, 4, '0', STR_PAD_LEFT);
            }
        });
    }
}
