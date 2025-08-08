<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
     protected $fillable = [
        'name',
        'description',
        'stock',
        'price',
        'merchant_id',
        'category_id',
         'barcode'
     ];
      
   public function category()
      {
         return $this->belongsTo(Category::class);
      }

}
