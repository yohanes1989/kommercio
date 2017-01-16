<?php

namespace Kommercio\Http\Requests\Backend\Catalog;

use Kommercio\Http\Requests\Request;

class ProductCompositeFormRequest extends Request
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
        $rules = [
            'name' => 'required',
            'minimum' => 'required|numeric|min:0',
            'maximum' => 'required|numeric|min:0',
            'composite_product' => 'required_without:product_category|array',
            'composite_product.*' => 'numeric|exists:products,id',
            'product_category' => 'required_without:composite_product|array',
            'product_category.*' => 'numeric|exists:product_categories,id',
        ];

        return $rules;
    }
}