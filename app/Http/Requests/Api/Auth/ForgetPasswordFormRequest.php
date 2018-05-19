<?php

namespace Kommercio\Http\Requests\Api\Auth;

class ForgetPasswordFormRequest extends \Illuminate\Foundation\Http\FormRequest {
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules() {
        $rules = [
            'email' => [
                'required',
                'email',
            ],
        ];

        return $rules;
    }
}
