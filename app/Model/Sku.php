<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sku extends Model
{
    public function image() : BelongsTo
    {
        return $this->BelongsTo(ProductImage::Class, 'product_image_id', 'id');
    }
}
