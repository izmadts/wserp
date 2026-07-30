<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Product extends Model
{
    protected $fillable = [
        'code', 'name', 'slug', 'category_id', 'unit',
        'purchase_price', 'sale_price', 'wholesale_price',
        'current_stock', 'min_stock_level', 'max_stock_level',
        'barcode', 'description', 'image', 'is_active'
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'current_stock' => 'decimal:2',
        'min_stock_level' => 'decimal:2',
        'max_stock_level' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // Auto generate code and slug
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($product) {
            $product->slug = Str::slug($product->name);
            if (empty($product->code)) {
                $product->code = 'PROD-' . strtoupper(Str::random(4));
            }
        });

        static::updating(function ($product) {
            $product->slug = Str::slug($product->name);
        });
    }

    // Relationships
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock($query)
    {
        return $query->whereColumn('current_stock', '<=', 'min_stock_level');
    }

    // Helper methods
    public function isLowStock()
    {
        return $this->current_stock <= $this->min_stock_level;
    }

    public function addStock($quantity)
    {
        $this->current_stock += $quantity;
        $this->save();
        return $this;
    }

    public function removeStock($quantity)
    {
        if ($this->current_stock < $quantity) {
            throw new \Exception("Insufficient stock for product: {$this->name}");
        }
        $this->current_stock -= $quantity;
        $this->save();
        return $this;
    }
}