<?php

namespace Kommercio\Models;

use Carbon\Carbon;
use Dimsav\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Kommercio\Facades\FrontendHelper;
use Kommercio\Facades\ProjectHelper;
use Kommercio\Models\Interfaces\UrlAliasInterface;
use Kommercio\Models\Order\LineItem;
use Kommercio\Models\Order\Order;
use Kommercio\Models\Order\OrderLimit;
use Kommercio\Models\ProductAttribute\ProductAttributeValue;
use Kommercio\Traits\Frontend\ProductHelper as FrontendProductHelper;
use Kommercio\Traits\Model\SeoTrait;
use Kommercio\Facades\PriceFormatter;

class Product extends Model implements UrlAliasInterface
{
    use SoftDeletes, Translatable, SeoTrait, FrontendProductHelper;

    const TYPE_DEFAULT = 'default';

    const COMBINATION_TYPE_SINGLE = 'single';
    const COMBINATION_TYPE_VARIABLE = 'variable';
    const COMBINATION_TYPE_VARIATION = 'variation';

    protected $fillable = ['name', 'description_short', 'description', 'slug', 'manufacturer_id', 'meta_title', 'meta_description', 'locale',
        'sku', 'type', 'width', 'length', 'depth', 'weight'];
    protected $casts = [
        'active' => 'boolean',
        'available' => 'boolean',
    ];
    protected $dates = ['deleted_at'];
    private $_warehouse;
    private $_store;
    private $_productDetail;
    private $_retailPrice;
    private $_retailPriceWithTax;
    private $_netPrice;
    private $_netPriceWithTax;

    public $translatedAttributes = ['name', 'description_short', 'description', 'slug', 'meta_title', 'meta_description', 'locale', 'thumbnail', 'thumbnails', 'images'];

    //Relations
    public function defaultCategory()
    {
        return $this->belongsTo('Kommercio\Models\ProductCategory', 'default_category_id');
    }

    public function categories()
    {
        return $this->belongsToMany('Kommercio\Models\ProductCategory', 'category_product');
    }

    public function manufacturer()
    {
        return $this->belongsTo('Kommercio\Models\Manufacturer');
    }

    public function productDetails()
    {
        return $this->hasMany('Kommercio\Models\ProductDetail');
    }

    public function parent()
    {
        return $this->belongsTo('Kommercio\Models\Product', 'parent_id');
    }

    public function variations()
    {
        return $this->hasMany('Kommercio\Models\Product', 'parent_id')->where('combination_type', self::COMBINATION_TYPE_VARIATION);
    }

    public function productAttributes()
    {
        return $this->belongsToMany('Kommercio\Models\ProductAttribute\ProductAttribute', 'product_product_attribute')->withPivot(['product_attribute_value_id'])->orderBy('sort_order', 'ASC');
    }

    public function productAttributeValues()
    {
        return $this->belongsToMany('Kommercio\Models\ProductAttribute\ProductAttributeValue', 'product_product_attribute')
            ->withPivot(['product_attribute_id'])
            ->orderBy('sort_order', 'ASC');
    }

    public function productFeatures()
    {
        return $this->belongsToMany('Kommercio\Models\ProductFeature\ProductFeature', 'product_product_feature')->withPivot(['product_feature_value_id'])->orderBy('sort_order', 'ASC');
    }

    public function productFeatureValues()
    {
        return $this->belongsToMany('Kommercio\Models\ProductFeature\ProductFeatureValue', 'product_product_feature')->withPivot(['product_feature_id'])->orderBy('sort_order', 'ASC');
    }

    public function priceRules()
    {
        if($this->combination_type == self::COMBINATION_TYPE_VARIATION){
            return $this->hasMany('Kommercio\Models\PriceRule', 'variation_id')->orderBy('created_at', 'DESC');
        }else{
            return $this->hasMany('Kommercio\Models\PriceRule')->orderBy('created_at', 'DESC');
        }
    }

    public function cartPriceRules()
    {
        return $this->belongsToMany('Kommercio\Models\PriceRule\CartPriceRule');
    }

    public function warehouses()
    {
        return $this->belongsToMany('Kommercio\Models\Warehouse')->withPivot('stock');
    }

    public function orderLimits()
    {
        return $this->morphToMany('Kommercio\Models\Order\OrderLimit', 'order_limitable');
    }

    public function crossSellTo()
    {
        return $this->belongsToMany('Kommercio\Models\Product', 'related_products', 'product_id', 'target_id')->withPivot(['sort_order', 'type'])->orderBy('sort_order', 'ASC')->wherePivot('type', 'cross_sell');
    }

    public function crossSellBy()
    {
        return $this->belongsToMany('Kommercio\Models\Product', 'related_products', 'target_id', 'product_id')->withPivot(['sort_order', 'type'])->orderBy('sort_order', 'ASC')->wherePivot('type', 'cross_sell');
    }

    //Methods
    public function getExternalPath()
    {
        if($this->isVariation){
            $path = $this->getInternalPathSlug().'/'.$this->parent->id;
        }else{
            $path = $this->getInternalPathSlug().'/'.$this->id;
        }

        return FrontendHelper::get_url($path);
    }

    public function getUrlAlias()
    {
        $paths = [];

        $category = $this->defaultCategory;

        if($category){
            $paths[] = $category->getUrlAlias();
        }

        $paths[] = $this->slug;

        return implode('/', $paths);
    }

    public function getInternalPathSlug()
    {
        return 'product';
    }

    public function getBreadcrumbTrails()
    {
        $defaultCategory = $this->defaultCategory;

        $breadcrumbs = $defaultCategory->getBreadcrumbTrails();
        $breadcrumbs[] = $defaultCategory;

        return $breadcrumbs;
    }

    public function hasThumbnail()
    {
        return $this->thumbnails->count() > 0;
    }

    public function hasCategory($category)
    {
        if(is_int($category)){
            foreach($this->categories as $categoryObj){
                if($categoryObj->id == $category){
                    return true;
                }
            }
        }elseif(is_string($category)){
            foreach($this->categories as $categoryObj){
                if($categoryObj->slug == $category){
                    return true;
                }
            }
        }else{
            foreach($this->categories as $categoryObj){
                if($categoryObj->id == $category->id){
                    return true;
                }
            }
        }

        return false;
    }

    public function hasProductAttribute($productAttribute)
    {
        if(is_int($productAttribute)){
            foreach($this->productAttributes as $productAttributeObj){
                if($productAttributeObj->id == $productAttribute){
                    return true;
                }
            }
        }elseif(is_string($productAttribute)){
            foreach($this->productAttributes as $productAttributeObj){
                if($productAttributeObj->slug == $productAttribute){
                    return true;
                }
            }
        }else{
            foreach($this->productAttributes as $productAttributeObj){
                if($productAttributeObj->id == $productAttribute->id){
                    return true;
                }
            }
        }

        return false;
    }

    public function getRetailPrice($tax = false)
    {
        if(!isset($this->_retailPrice)){
            if($this->combination_type == self::COMBINATION_TYPE_VARIATION){
                $price = $this->productDetail->retail_price?$this->productDetail->retail_price:$this->parent->productDetail->retail_price;
            }else{
                $price = isset($this->productDetail)?$this->productDetail->retail_price:null;
            }

            $priceRules = $this->getSpecificPriceRules(FALSE);

            foreach($priceRules as $priceRule){
                if($priceRule->validateProduct($this)){
                    $price = $priceRule->getValue($price);
                }
            }

            $this->_retailPrice = $price;
        }

        if(!isset($this->_retailPriceWithTax)){
            if($this->productDetail->taxable){
                $this->_retailPriceWithTax = $this->_retailPrice + $this->calculateTax($this->_retailPrice);
            }else{
                $this->_retailPriceWithTax = $this->_retailPrice;
            }
        }

        return $tax?$this->_retailPriceWithTax:$this->_retailPrice;
    }

    public function getNetPrice($tax = false)
    {
        if(!isset($this->_netPrice)){
            $catalogPriceRules = $this->getCatalogPriceRules();

            $price = $this->getRetailPrice();

            $specificDiscountPriceRules = $this->getSpecificPriceRules(TRUE);

            foreach($specificDiscountPriceRules as $specificDiscountPriceRule){
                if($specificDiscountPriceRule->validateProduct($this)){
                    $price = $specificDiscountPriceRule->getValue($price);
                }
            }

            foreach($catalogPriceRules as $catalogPriceRule){
                if($catalogPriceRule->validateProduct($this)){
                    $price = $catalogPriceRule->getValue($price);
                }
            }

            $this->_netPrice = $price;
        }

        if(!isset($this->_netPriceWithTax)){
            if($this->productDetail->taxable){
                $this->_netPriceWithTax = $this->_netPrice + $this->calculateTax($this->_netPrice);
            }else{
                $this->_netPriceWithTax = $this->_netPrice;
            }
        }

        return $tax?$this->_netPriceWithTax:$this->_netPrice;
    }

    public function getOldPrice($tax = false)
    {
        if($this->getRetailPrice($tax) - $this->getNetPrice($tax) == 0){
            return FALSE;
        }

        return $this->getRetailPrice($tax);
    }

    public function getProductAttributeValue($attribute)
    {
        $productAttributeValue = null;

        foreach($this->productAttributeValues as $productAttributeValue){
            if($attribute == $productAttributeValue->product_attribute_id){
                return $productAttributeValue;
            }
        }

        return $productAttributeValue;
    }

    public function getSiblingByAttribute($attribute, $attributeValue)
    {
        if($this->combination_type == self::COMBINATION_TYPE_VARIATION){
            $variations = $this->parent->variations;
        }else{
            $variations = $this->variations;
        }

        $sibling = null;

        $compareableValues = [];

        foreach($this->productAttributeValues as $productAttributeValue){
            if($productAttributeValue->pivot->product_attribute_id != $attribute){
                $compareableValues[] = $productAttributeValue->id;
            }else{
                $compareableValues[] = $attributeValue;
            }
        }

        $compareableValuesCount = count($compareableValues);

        foreach($variations as $variation){
            if($variation->productDetail->active && count(array_intersect($compareableValues, $variation->productAttributeValues->pluck('id')->all())) == $compareableValuesCount){
                $sibling = $variation;
                break;
            }
        }

        return $sibling;
    }

    public function getProductAttributeWithValues()
    {
        if(!$this->relationLoaded('productAttributes')){
            $this->load('productAttributes');
        }

        $array = [];

        foreach($this->productAttributes as $productAttribute){
            $array[$productAttribute->id] = $productAttribute->pivot->productAttributeValue->id;
        }

        return $array;
    }

    public function getVariationsByAttributes($attributes, $attributeValues)
    {
        $variationsQb = $this->variations();

        $join = with(new self())->productAttributes();

        foreach($attributes as $attribute){
            $variationsQb->leftJoin($join->getTable().' AS A'.$attribute, 'A'.$attribute.'.product_id', '=', $join->getQualifiedParentKeyName());
            $variationsQb->where('A'.$attribute.'.product_attribute_value_id', $attributeValues[$attribute]);
        }

        $variations = $variationsQb->get();

        return $variations;
    }

    public function getProductFeatureValue($feature_id)
    {
        $features = $this->getProductFeaturesWithValues();

        $value = null;
        if(isset($features[$feature_id])){
            $value = $features[$feature_id];
        }

        return $value;
    }

    public function getProductFeaturesWithValues()
    {
        if(!$this->relationLoaded('productFeatures')){
            $this->load('productFeatures');
        }

        $array = [];

        foreach($this->productFeatures as $productFeature){
            $array[$productFeature->id] = $productFeature->pivot->productFeatureValue->id;
        }

        return $array;
    }

    protected function calculateTax($price)
    {
        $taxTotal = 0;
        $taxes = $this->store->getTaxes();

        foreach($taxes as $tax){
            $taxValue = [
                'net' => 0,
                'gross' => 0,
                'rate_total' => 0
            ];

            $taxValue['gross'] = PriceFormatter::round($tax->calculateTax($price));
            $taxValue['net'] = PriceFormatter::round($taxValue['gross']);
            $taxValue['rate_total'] += $tax->rate;

            $taxTotal += $taxValue['net'];
        }

        return $taxTotal;
    }

    public function getStock($warehouse_id=null)
    {
        if($this->productDetail && !$this->productDetail->manage_stock){
            return null;
        }

        $warehouses = $this->warehouses;

        if(!$warehouse_id){
            $warehouse_id = $this->warehouse->id;
        }

        $warehouse = $warehouses->find($warehouse_id);

        return $warehouse?$warehouse->pivot->stock+0:0;
    }

    public function checkStock($amount, $warehouse_id=null)
    {
        $productDetail = $this->productDetail;

        if(!$warehouse_id){
            $defaultWarehouse = $this->store->getDefaultWarehouse();
            $warehouse_id = $defaultWarehouse?$defaultWarehouse->id:null;
        }

        if($productDetail->manage_stock && $warehouse_id){
            $existingStock = $this->getStock($warehouse_id);

            return $existingStock - $amount >= 0;
        }

        return TRUE;
    }

    public function increaseStock($amount, $warehouse_id=null)
    {
        if(!$warehouse_id){
            $defaultWarehouse = $this->store->getDefaultWarehouse();
            $warehouse_id = $defaultWarehouse?$defaultWarehouse->id:null;
        }

        $productDetail = $this->productDetail;

        if($productDetail->manage_stock && $warehouse_id){
            $existingStock = $this->getStock($warehouse_id);

            $this->saveStock(($existingStock + $amount + 0), $warehouse_id);
        }
    }

    public function reduceStock($amount, $warehouse_id=null)
    {
        if(!$warehouse_id){
            $defaultWarehouse = $this->store->getDefaultWarehouse();
            $warehouse_id = $defaultWarehouse?$defaultWarehouse->id:null;
        }

        $productDetail = $this->productDetail;

        if($productDetail->manage_stock && $warehouse_id){
            $existingStock = $this->getStock($warehouse_id);

            $this->saveStock(($existingStock - $amount + 0), $warehouse_id);
        }
    }

    public function saveStock($stock, $warehouse_id=null)
    {
        if(!is_null($stock)){
            if(!$warehouse_id){
                $defaultWarehouse = $this->store->getDefaultWarehouse();
                $warehouse_id = $defaultWarehouse?$defaultWarehouse->id:null;
            }

            if($warehouse_id){
                $this->warehouses()->sync([
                    $warehouse_id => ['stock' => $stock]
                ]);
            }
        }
    }

    public function getSpecificPriceRules($is_discount = NULL)
    {
        //Get parent all attributes price rules if variation
        if($this->combination_type == self::COMBINATION_TYPE_VARIATION){
            $qb = $this->parent->priceRules()->whereNull('variation_id')->active();

            if($is_discount === TRUE){
                $qb->isDiscount();
            }elseif($is_discount === FALSE){
                $qb->isNotDiscount();
            }

            $parentPriceRules = $qb->get();
        }

        $qb = $this->priceRules()->active();

        if($is_discount === TRUE){
            $qb->isDiscount();
        }elseif($is_discount === FALSE){
            $qb->isNotDiscount();
        }

        $priceRules = $qb->get();

        if(isset($parentPriceRules)){
            $priceRules = $priceRules->merge($parentPriceRules);
        }

        return $priceRules;
    }

    public function getCatalogPriceRules()
    {
        $qb = PriceRule::notProductSpecific()->active()->orderBy('sort_order', 'ASC');

        $qb->where(function($qb){
            $categories = $this->categories;
            $manufacturer = $this->manufacturer_id;
            $features = $this->productFeatureValues;
            $attributeValueIds = [];

            if($this->isVariation){
                $attributeValues = $this->productAttributeValues;
                $attributeValueIds = $attributeValues->pluck('id')->all();
            }else{
                if($this->variations->count() > 0){
                    $attributeValues = ProductAttributeValue::whereHas('products', function($query){
                        $query->whereIn('product_id', $this->variations->pluck('id')->all());
                    })->get();
                    $attributeValueIds = $attributeValues->pluck('id')->all();
                }
            }

            $firstValidation = true;

            if($categories->count() > 0){
                $validationFunction = $firstValidation?'whereHas':'orWhereHas';

                $qb->$validationFunction('priceRuleOptionGroups.categories', function($query) use ($categories){
                    $query->whereIn('id', $categories->pluck('id')->all());
                });
                $firstValidation = false;
            }

            if($features->count() > 0){
                $validationFunction = $firstValidation?'whereHas':'orWhereHas';

                $qb->$validationFunction('priceRuleOptionGroups.featureValues', function($query) use ($features){
                    $query->whereIn('id', $features->pluck('id')->all());
                });
                $firstValidation = false;
            }

            if($manufacturer){
                $validationFunction = $firstValidation?'whereHas':'orWhereHas';

                $qb->$validationFunction('priceRuleOptionGroups.manufacturers', function($query) use ($manufacturer){
                    $query->whereIn('id', [$manufacturer]);
                });
                $firstValidation = false;
            }

            if($attributeValueIds){
                $validationFunction = $firstValidation?'whereHas':'orWhereHas';

                $qb->$validationFunction('priceRuleOptionGroups.attributeValues', function($query) use ($attributeValueIds){
                    $query->whereIn('id', $attributeValueIds);
                });
                $firstValidation = false;
            }
        });

        $includedPriceRules = $qb->get();

        return $includedPriceRules;
    }

    public function getOrderCount($options = [])
    {
        $qb = LineItem::isProduct($this->id)
            ->whereHas('order', function($query) use ($options){
                $query->usageCounted();

                if(!empty($options['delivery_date'])){
                    $query->whereRaw('DATE_FORMAT(delivery_date, \'%Y-%m-%d\') = ?', [$options['delivery_date']]);
                }

                if(!empty($options['checkout_at'])){
                    $query->whereRaw('DATE_FORMAT(checkout_at, \'%Y-%m-%d\') = ?', [$options['checkout_at']]);
                }
            });

        $orderCount = floatval($qb->sum('quantity'));

        return $orderCount;
    }

    public function getPerOrderLimit($options = [])
    {
        $store = isset($options['store'])?$options['store']:null;
        $date = isset($options['date'])?Carbon::createFromFormat('Y-m-d', $options['date']):null;

        //Per Order Limit
        $orderLimitsQb = $this->getOrderLimitQb(OrderLimit::LIMIT_PER_ORDER, $date, $store);
        $orderLimits = $orderLimitsQb->get();

        $orderLimit = ($orderLimits->count() > 0)?$this->extractOrderLimit($orderLimits)->limit:null;

        return $orderLimit;
    }

    public function getOrderLimit($options = [])
    {
        $store = !empty($options['store'])?$options['store']:null;
        $date = !empty($options['date'])?Carbon::createFromFormat('Y-m-d', $options['date']):null;
        $deliveryDate = !empty($options['delivery_date'])?Carbon::createFromFormat('Y-m-d', $options['delivery_date']):null;

        $deliveryOrderLimit = null;

        if($deliveryDate){
            //Delivery Limit
            $deliveryOrderLimitsQb = $this->getOrderLimitQb(OrderLimit::LIMIT_DELIVERY_DATE, $deliveryDate, $store);
            $deliveryOrderLimits = $deliveryOrderLimitsQb->get();

            $deliveryOrderLimit = ($deliveryOrderLimits->count() > 0)?$this->extractOrderLimit($deliveryOrderLimits)->limit:null;
        }

        //Order Total Limit
        $totalOrderLimit = null;
        if($date){
            $totalOrderLimitsQb = $this->getOrderLimitQb(OrderLimit::LIMIT_ORDER_DATE, $date, $store);
            $totalOrderLimits = $totalOrderLimitsQb->get();

            $totalOrderLimit = ($totalOrderLimits->count() > 0)?$this->extractOrderLimit($totalOrderLimits)->limit:null;
        }

        $orderLimits = [
            'delivery_date' => $deliveryOrderLimit,
            'checkout_at' => $totalOrderLimit
        ];

        foreach($orderLimits as $idx=>$orderLimit){
            if(is_null($orderLimit)){
                unset($orderLimits[$idx]);
            }
        }

        $limitType = 'checkout_at';

        if(isset($orderLimits['checkout_at']) && $totalOrderLimit <= $deliveryOrderLimit){
            $limitType = 'checkout_at';
        }elseif(isset($orderLimits['delivery_date'])){
            $limitType = 'delivery_date';
        }

        return $orderLimits?['limit_type' => $limitType, 'limit' => $orderLimits[$limitType]]:null;
    }

    public function getUnavailableDeliveryDates($options)
    {
        $disabledDates = [];
        $quantity = !empty($options['quantity'])?$options['quantity']:0;
        $saved_quantity = !empty($options['saved_quantity'])?$options['saved_quantity']:0;
        $saved_delivery_date = !empty($options['saved_delivery_date'])?$options['saved_delivery_date']:null;
        $quantity = !empty($options['quantity'])?$options['quantity']:0;
        $store = !empty($options['store'])?$options['store']:null;
        $months = !empty($options['months'])?$options['months']:[];
        $format = !empty($options['format'])?$options['format']:'Y-m-d';

        if(!$months){
            throw new \Exception('You need to specify months.');
        }

        foreach($months as $month){
            $dayToRun = Carbon::createFromFormat('j-n-Y', '1-'.$month);
            $dayToRun->setTime(0, 0, 0);

            $lastDayOfMonth = clone $dayToRun;
            $lastDayOfMonth->modify('last day of this month');

            while($dayToRun->lte($lastDayOfMonth)){
                $dayOrderCount = $this->getOrderCount([
                    'delivery_date' => $dayToRun->format('Y-m-d')
                ]);

                if($dayToRun->format('j-n-Y') == $saved_delivery_date){
                    $dayOrderCount -= $saved_quantity;
                }

                $dayOrderLimit = $this->getOrderLimit([
                    'store' => $store,
                    'delivery_date' => $dayToRun->format($format)
                ]);

                if(is_array($dayOrderLimit) && $dayOrderCount + $quantity > $dayOrderLimit['limit']){
                    $disabledDates[] = $dayToRun->format($format);
                }

                $dayToRun->addDay();
            }
        }

        return $disabledDates;
    }

    public function getViewSuggestions()
    {
        $viewSuggestions = [];

        if($this->defaultCategory){
            $viewSuggestions[] = 'frontend.catalog.product.view_category_'.$this->defaultCategory->id;
        }

        $viewSuggestions += ['frontend.catalog.product.view_'.$this->id, 'frontend.catalog.product.view'];

        return $viewSuggestions;
    }

    protected function extractOrderLimit($orderLimits)
    {
        $sorted = [
            'has_date' => [
                OrderLimit::TYPE_PRODUCT => [],
                OrderLimit::TYPE_PRODUCT_CATEGORY => []
            ],
            'no_date' => [
                OrderLimit::TYPE_PRODUCT => [],
                OrderLimit::TYPE_PRODUCT_CATEGORY => []
            ]
        ];

        //Has date
        foreach($orderLimits as $orderLimit){
            if($orderLimit->hasDate()){
                $sorted['has_date'][$orderLimit->type][] = $orderLimit;
            }
        }

        //No date
        foreach($orderLimits as $orderLimit){
            if(!$orderLimit->hasDate()){
                $sorted['no_date'][$orderLimit->type][] = $orderLimit;
            }
        }

        foreach($sorted['has_date'] as $sortedWalk){
            if(!empty($sortedWalk)){
                return $sortedWalk[0];
            }
        }

        foreach($sorted['no_date'] as $sortedWalk){
            if(!empty($sortedWalk)){
                return $sortedWalk[0];
            }
        }
    }

    protected function getOrderLimitQb($limit_type, $date, $store)
    {
        $qb = OrderLimit::active()
            ->orderBy('created_at', 'ASC')
            ->whereLimitType($limit_type)
            ->where(function($qb){
                $qb->whereHas('products', function($qb){
                    $qb->whereIn('id', [$this->id]);
                })
                ->orWhereHas('productCategories', function($qb){
                    $qb->whereIn('id', $this->categories->pluck('id')->all());
                });
            });

        if($store){
            $qb->whereStore($store);
        }

        if($date){
            $qb->withinDate($date);
        }else{
            $qb->allDays();
        }

        return $qb;
    }

    //Accessors
    public function getProductDetailAttribute()
    {
        if(!isset($this->_productDetail)){
            $this->_productDetail = $this->productDetails()->where('store_id', $this->store->id)->first();
        }

        return $this->_productDetail;
    }

    public function getIsVariationAttribute()
    {
        return $this->combination_type == self::COMBINATION_TYPE_VARIATION;
    }

    public function getIsPurchaseableAttribute()
    {
        return in_array($this->combination_type, [self::COMBINATION_TYPE_VARIATION, self::COMBINATION_TYPE_SINGLE]);
    }

    public function getStoreAttribute()
    {
        if(!$this->_store){
            $this->_store = ProjectHelper::getActiveStore();
        }

        return $this->_store;
    }

    public function getWarehouseAttribute()
    {
        if(!$this->_warehouse){
            $store = $this->store;

            $this->_warehouse = $store->getDefaultWarehouse();
        }

        return $this->_warehouse;
    }

    //Mutators
    public function setStoreAttribute($store_id)
    {
        $store = Store::find($store_id);
        $this->_store = $store;
    }

    public function setWarehouseAttribute($warehouse_id)
    {
        $warehouse = Warehouse::find($warehouse_id);

        $this->_warehouse = $warehouse;
    }

    //Scopes
    public function scopeActive($query)
    {
        $store = ProjectHelper::getActiveStore();

        $query->whereHas('productDetails', function($query) use ($store){
            $query->where('active', true)->where('store_id', $store->id);
        });
    }

    public function scopeCatalogVisible($query)
    {
        $store = ProjectHelper::getActiveStore()->id;

        $query->whereHas('productDetail', function($query){
            $query->whereIn('visibility', [ProductDetail::VISIBILITY_CATALOG, ProductDetail::VISIBILITY_EVERYWHERE]);
        });
    }

    public function scopeSearchVisible($query)
    {
        $store = ProjectHelper::getActiveStore()->id;

        $query->whereHas('productDetail', function($query, $store){
            $query->whereIn('visibility', [ProductDetail::VISIBILITY_SEARCH, ProductDetail::VISIBILITY_EVERYWHERE]);
        });
    }

    public function scopeProductEntity($query)
    {
        $query->whereNotIn('combination_type', [self::COMBINATION_TYPE_VARIATION]);
    }

    public function scopeProductSelection($query)
    {
        $query->whereNotIn('combination_type', [self::COMBINATION_TYPE_VARIABLE]);
    }

    public function scopeStickyLineItem($query)
    {
        $query->where('sticky_line_item', 1);
    }

    public function scopeSelectSelf($query)
    {
        $query->selectRaw($this->getTable().'.*');
    }

    public function scopeJoinTranslation($query, $locale=null)
    {
        $locale = $locale?$locale:$this->locale();

        $query
            ->leftJoin($this->getTranslationsTable().' as T', function($join) use ($locale){
                $join->on('T.product_id', '=', $this->getTable().'.id')
                    ->where('T.'.$this->getLocaleKey(), '=', $locale);
            });

        $query->addSelect(DB::raw('T.*'));
    }

    public function scopeJoinDetail($query, $store=null)
    {
        $store = $store?$store:ProjectHelper::getActiveStore()->id;

        $productDetailTable = $this->productDetails()->getRelated()->getTable();

        $query->leftJoin($productDetailTable.' AS D', function($join) use ($productDetailTable, $store){
            $join->on('D.'.$this->productDetails()->getPlainForeignKey(), '=', $this->getTable().'.id')
                ->where('D.store_id', '=', $store);
        });

        $query->addSelect(DB::raw('D.*', 'D.id AS detail_id'));
    }

    public function scopeWithDetail($query, $store=null)
    {
        $store = $store?$store:ProjectHelper::getActiveStore()->id;

        $query->with(['productDetail' => function($query) use ($store){
            $query->where('store_id', $store);
        }]);
    }

    public function scopeWhereDetail($query, $key, $value, $operator='=', $store=null)
    {
        $store = $store?$store:ProjectHelper::getActiveStore()->id;

        $query->whereHas('productDetail', function($query) use ($key, $value, $operator, $store){
            $query->where('store_id', $store)->where($key, $operator, $value);
        });
    }

    //Statics
    public static function getTypeOptions($option=null)
    {
        $array = [
            self::TYPE_DEFAULT => 'Default',
        ];

        if(empty($option)){
            return $array;
        }

        return (isset($array[$option]))?$array[$option]:$array;
    }

    public static function getCombinationTypeOptions($option=null)
    {
        $array = [
            self::COMBINATION_TYPE_SINGLE => 'Single Product',
            self::COMBINATION_TYPE_VARIABLE => 'Variable Product',
        ];

        if(empty($option)){
            return $array;
        }

        return (isset($array[$option]))?$array[$option]:$array;
    }
}
