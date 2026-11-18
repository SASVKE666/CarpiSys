<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Location extends Model {
    protected $fillable = ['name','description','is_active'];
    public function stocks() { return $this->hasMany(InventoryStock::class); }
}