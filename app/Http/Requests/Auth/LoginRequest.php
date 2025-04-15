<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'email' => 'required|email',
            'password' => 'required|string|min:8'
        ];
    }

    public function messages()
    {
        return [
            'email.required' => config('messages.authentification.errors.validation.email.required'),
            'email.email' => config('messages.authentification.errors.validation.email.invalid'),
            'password.required' => config('messages.authentification.errors.validation.password.required'),
            'password.min' => config('messages.authentification.errors.validation.password.min_length')
        ];
    }
}
