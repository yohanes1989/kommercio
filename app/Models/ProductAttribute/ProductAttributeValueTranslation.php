<?php

namespace Kommercio\Models\ProductAttribute;

use Kommercio\Models\Abstracts\SluggableModel;

class ProductAttributeValueTranslation extends SluggableModel
{
    public $timestamps = FALSE;

    //Relations
    public function productAttributeValue()
    {
        return $this->belongsTo('Kommercio\Models\ProductAttribute\ProductAttributeValue');
    }
}
