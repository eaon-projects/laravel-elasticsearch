<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    public function skus()
    {
        return $this->hasMany(Sku::class)->orderBy('sort');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort');
    }
}
