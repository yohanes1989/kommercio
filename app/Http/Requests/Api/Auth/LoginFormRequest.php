<?php

namespace Kommercio\Http\Requests\Api\Auth;

class LoginFormRequest extends \Illuminate\Foundation\Http\FormRequest {
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
            'email' => 'required',
            'password' => 'required',
        ];

        return $rules;
    }
}
