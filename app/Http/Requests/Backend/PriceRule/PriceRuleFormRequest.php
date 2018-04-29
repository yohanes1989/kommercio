<?php

namespace Kommercio\Http\Requests\Backend\PriceRule;

use Kommercio\Facades\CurrencyHelper;
use Kommercio\Http\Requests\Request;
use Kommercio\Models\PriceRule;
use Kommercio\Models\Product;
use Kommercio\Models\Store;

class PriceRuleFormRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $allowedCurrencies = implode(',', array_keys(CurrencyHelper::getActiveCurrencies()));
        $allowedStores = implode(',', array_keys(Store::getStoreOptions()));
        $allowedModificationType = implode(',', array_keys(PriceRule::getModificationTypeOptions()));

        $rules = [
            'price_rule.price' => 'required_without:price_rule.modification|nullable|numeric|min:0',
            'price_rule.modification' => 'required_without:price_rule.price|nullable|numeric',
            'price_rule.modification_type' => 'nullable|in:'.$allowedModificationType,
            'price_rule.store_id' => 'nullable|in:'.$allowedStores,
            'price_rule.currency' => 'nullable|in:'.$allowedCurrencies,
            'price_rule.active_date_from' => 'nullable|date_format:Y-m-d H:i',
            'price_rule.active_date_to' => 'nullable|date_format:Y-m-d H:i',
        ];

        if($this->route('product_id')){
            $product = Product::findOrFail($this->route('product_id'));
            $allowedVariations = $product->variations->pluck('id')->all();

            if($allowedVariations){
                $rules['price_rule.variation_id'] = 'nullable|in:'.implode(',', $allowedVariations);
            }
        }else{
            $rules['price_rule.name'] = 'required';
        }

        return $rules;
    }

    public function all($keys = null)
    {
        $attributes = parent::all($keys);

        //Remove empty price rule options
        if($this->filled('options')){
            foreach($attributes['options'] as $idx=>$optionGroup){
                $empty = TRUE;

                foreach($optionGroup as $option){
                    if(!empty($option)){
                        $empty = FALSE;
                        break;
                    }
                }

                if($empty){
                    unset($attributes['options'][$idx]);
                }
            }
        }

        if(!$this->filled('price_rule')){
            $attributes['price_rule'] = $attributes;
        }

        $this->replace($attributes);

        if(!$this->filled('price_rule.price')){
            $attributes['price_rule']['price'] = null;
        }
        if(!$this->filled('price_rule.modification')){
            $attributes['price_rule']['modification'] = null;
        }
        if(!$this->filled('price_rule.variation_id')){
            $attributes['price_rule']['variation_id'] = null;
        }
        if(!$this->filled('price_rule.currency')){
            $attributes['price_rule']['currency'] = null;
        }
        if(!$this->filled('price_rule.store_id')){
            $attributes['price_rule']['store_id'] = null;
        }
        if(!$this->filled('price_rule.active')){
            $attributes['price_rule']['active'] = false;
        }
        if(!$this->filled('price_rule.is_discount')){
            $attributes['price_rule']['is_discount'] = false;
        }

        $this->replace($attributes);

        return parent::all($keys);
    }
}
