<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class OverheadRate extends Model {
    protected $fillable = [
        'name', 'rate_type', 'value', 'description', 'is_active'
    ];

    protected $casts = [
        'value'     => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function scopeActive($q) { return $q->where('is_active', true); }
}