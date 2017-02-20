<?php

namespace Kommercio\Traits\Frontend;

use Kommercio\Facades\Shortcode;
use Kommercio\Models\Customer;
use Kommercio\Models\Product;
use Kommercio\Models\ProductAttribute\ProductAttributeValue;

trait ProductHelper
{
    //'name', 'width', 'length', 'depth', 'weight', 'thumbnail', 'images', 'thumbnails';
    public function getName()
    {
        $name = $this->name;

        if($this->combination_type == Product::COMBINATION_TYPE_VARIATION){
            $name = $this->parent->name.' '.$this->printProductAttributes(false);
        }

        return $name;
    }

    public function getShortDescription()
    {
        return Shortcode::doShortcode($this->description_short);
    }

    public function getDescription()
    {
        return Shortcode::doShortcode($this->description);
    }

    public function getActiveVariations()
    {
        $variations = [];

        foreach($this->variations as $variation){
            if($variation->productDetail->active){
                $variations[] = $variation;
            }
        }

        return $variations;
    }

    public function getInStockVariations()
    {
        $return = [];

        foreach($this->variations as $variation){
            if($variation->checkStock(1)){
                $return[] = $variation;
            }
        }

        return $return;
    }

    public function isInStock()
    {
        $return = true;

        if($this->combination_type == Product::COMBINATION_TYPE_VARIABLE){
            return count($this->getInStockVariations()) > 0;
        }else{
            $return = $this->checkStock(1);
        }

        return $return;
    }

    public function getDefaultVariation()
    {
        $variations = $this->getActiveVariations();

        return isset($variations[0])?$variations[0]:$this;
    }

    public function getSelectableAttributes()
    {
        $attributes = [];

        foreach($this->getActiveVariations() as $variation){
            foreach($variation->productAttributeValues as $attributeValue){
                if(!isset($attributes[$attributeValue->productAttribute->id])){
                    $attributes[$attributeValue->productAttribute->id] = [
                        'id' => $attributeValue->productAttribute->id,
                        'label' => $attributeValue->productAttribute->name,
                        'object' => $attributeValue->productAttribute,
                        'options' => []
                    ];
                }

                $attributes[$attributeValue->productAttribute->id]['options'][$attributeValue->id] = [
                    'label' => $attributeValue->name,
                    'id' => $attributeValue->id,
                    'object' => $attributeValue
                ];
            }
        }

        return $attributes;
    }

    public function getVariationsGroupedByAttribute($attribute_id = null)
    {
        $array = [];

        foreach($this->getActiveVariations() as $variation){
            foreach($variation->productAttributeValues as $productAttributeValue){
                if(!$attribute_id || $attribute_id == $productAttributeValue->product_attribute_id){
                    $array[$productAttributeValue->product_attribute_id][$productAttributeValue->id][$variation->id] = $variation;
                }
            }
        }

        return $array;
    }

    public function getThumbnails()
    {
        $thumbnails = $this->thumbnails;

        if($this->combination_type == Product::COMBINATION_TYPE_VARIATION){
            if($thumbnails->count() < 1){
                $thumbnails = $this->parent->thumbnails;
            }
        }

        return $thumbnails;
    }

    public function getThumbnail()
    {
        $thumbnails = $this->getThumbnails();

        return $thumbnails->count() > 0?$thumbnails->get(0):null;
    }

    public function hasThumbnail()
    {
        return $this->getThumbnail()?true:false;
    }

    public function getImages()
    {
        $images = $this->images;

        if($this->combination_type == Product::COMBINATION_TYPE_VARIATION){
            if($images->count() < 1){
                $images = $this->parent->images;
            }
        }

        return $images;
    }

    public function getShippingInformation()
    {
        $array = [
            'width' => $this->width,
            'length' => $this->length,
            'depth' => $this->depth,
            'weight' => $this->weight,
        ];

        if($this->combination_type == Product::COMBINATION_TYPE_VARIATION){
            foreach($array as $idx => &$arrayItem){
                if(is_null($arrayItem)){
                    $arrayItem = $this->parent->{$idx};
                }
            }
        }

        return $array;
    }

    public function getProductAttributeForPrint()
    {
        $return = [];

        foreach($this->productAttributes as $productAttribute){
            $productAttributeValue = ProductAttributeValue::find($productAttribute->pivot->product_attribute_value_id);

            $return[] = [
                'label' => $productAttribute->name,
                'value' => $productAttributeValue?$productAttributeValue->name:''
            ];
        }

        return $return;
    }

    public function printProductAttributes($withLabel = true)
    {
        $returns = [];

        foreach($this->getProductAttributeForPrint() as $attribute){
            $returns[] = ($withLabel?$attribute['label'].': ':'').$attribute['value'];
        }

        return implode(' ', $returns);
    }

    public function getSimilarProducts($options = [])
    {
        $options['product_ids'] = [$this->id];

        return self::querySimilarProducts($options);
    }

    public function getPathToComposite()
    {
        return route('frontend.catalog.product.composite.view', ['slug' => $this->productCompositeGroup->slug, 'product_slug' => $this->slug]);
    }

    public function bookmarked(Customer $customer, $type)
    {
        $bookmark = $customer->bookmarks->filter(function($bookmark) use ($type){
            return $bookmark->bookmarkType->slug == $type;
        })->first();

        if($bookmark){
            return $bookmark->products->pluck('id')->contains($this->id);
        }

        return false;
    }

    public static function querySimilarProducts($options = [])
    {
        $qb = self::whereNotIn('id', $options['product_ids'])->productEntity()->active()->catalogVisible()->whereHas('categories', function($query) use ($options){
            $categories = [];

            foreach($options['product_ids'] as $product_id){
                $product = Product::findOrFail($product_id);

                foreach($product->categories as $category){
                    $categories[$category->id] = $category->id;
                }
            }

            $query->whereIn('id', $categories);
        });

        if(isset($options['limit'])){
            $qb->take($options['limit']);
        }

        if(isset($options['order_by'])){
            $qb->orderBy($options['order_by']);
        }

        if(isset($options['order_dir'])){
            $qb->orderBy($options['order_dir']);
        }

        return $qb->get();
    }
}