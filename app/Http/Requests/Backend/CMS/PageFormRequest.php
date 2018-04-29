<?php

namespace Kommercio\Http\Requests\Backend\CMS;

use Kommercio\Http\Requests\Request;

class PageFormRequest extends Request
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
        $pageId = $this->route('id');

        $rules = [
            'name' => 'required',
            'parent_id' => 'nullable|integer|not_in:'.$pageId,
            'slug' => 'required',
            'sort_order' => 'nullable|integer'
        ];

        return $rules;
    }

    public function all($keys = null)
    {
        $attributes = parent::all($keys);

        if(empty($attributes['parent_id'])){
            $attributes['parent_id'] = null;
        }

        if(!isset($attributes['active'])){
            $attributes['active'] = 0;
        }

        if(empty($attributes['sort_order'])){
            $attributes['sort_order'] = 0;
        }

        $this->replace($attributes);

        return parent::all($keys);
    }
}
