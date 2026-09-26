<?php

namespace App\Models;

use App\Services\ExchangeRateService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    protected $fillable = ['sku', 'name', 'product_category_id', 'description', 'unit', 'price', 'cost', 'currency_code', 'taxable', 'status'];

    protected $casts = ['price' => 'decimal:2', 'cost' => 'decimal:2', 'taxable' => 'boolean'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function countries(): BelongsToMany
    {
        return $this->belongsToMany(Country::class);
    }

    /** Selling price converted into another currency at today's rate. */
    public function priceIn(string $currency): float
    {
        return app(ExchangeRateService::class)->convert((float) $this->price, $this->currency_code, $currency);
    }
}
