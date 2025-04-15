<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:100',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'required|string|min:9|max:20|regex:/^\+?[0-9]{9,20}$/',
            'role' => 'required|in:organizer,participant'
        ];
    }

    public function messages()
    {
        return [
            'name.required' => config('messages.authentification.errors.validation.name.required'),
            'name.max' => config('messages.authentification.errors.validation.name.max_length'),
            'password.required' => config('messages.authentification.errors.validation.password.required'),
            'password.min' => config('messages.authentification.errors.validation.password.min_length'),
            'password.confirmed' => config('messages.authentification.errors.validation.password.mismatch'),
            'phone.required' => config('messages.authentification.errors.validation.phone.required'),
            'phone.min' => config('messages.authentification.errors.validation.phone.min_length'),
            'phone.max' => config('messages.authentification.errors.validation.phone.max_length'),
            'phone.regex' => config('messages.authentification.errors.validation.phone.invalid_format'),
            'role.required' => config('messages.authentification.errors.validation.role.required'),
            'role.in' => config('messages.authentification.errors.validation.role.invalid')
        ];
    }
}
