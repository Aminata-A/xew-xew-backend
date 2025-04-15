<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|min:9|max:20|regex:/^\+?[0-9]{9,20}$/',
            'role' => 'nullable|in:organizer,participant',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048'
        ];
    }

    public function messages()
    {
        return [
            'name.max' => config('messages.authentification.errors.validation.name.max_length'),
            'phone.min' => config('messages.authentification.errors.validation.phone.min_length'),
            'phone.max' => config('messages.authentification.errors.validation.phone.max_length'),
            'phone.regex' => config('messages.authentification.errors.validation.phone.invalid_format'),
            'role.in' => config('messages.authentification.errors.validation.role.invalid'),
            'photo.image' => 'Le fichier doit être une image',
            'photo.mimes' => 'L\'image doit être au format jpeg, png, jpg, gif ou svg',
            'photo.max' => 'L\'image ne peut pas dépasser 2 Mo'
        ];
    }
}
