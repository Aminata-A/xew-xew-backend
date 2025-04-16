<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

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
            'password' => 'required|string|min:6|confirmed',
            'phone' => 'required|string|min:9|max:20|regex:/^(\+?\d{1,4})?[0-9]{9,}$/',
            'role' => 'required|in:organizer,participant',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'Veuillez entrer votre nom.',
            'name.string' => 'Le nom doit être une chaîne de caractères.',
            'name.max' => 'Le nom ne doit pas dépasser 100 caractères.',

            'password.required' => 'Veuillez entrer un mot de passe.',
            'password.string' => 'Le mot de passe doit être une chaîne de caractères.',
            'password.min' => 'Le mot de passe doit contenir au moins 6 caractères.',
            'password.confirmed' => 'Les mots de passe ne correspondent pas.',

            'phone.required' => 'Veuillez entrer votre numéro de téléphone.',
            'phone.string' => 'Le numéro de téléphone doit être une chaîne de caractères.',
            'phone.min' => 'Le numéro de téléphone doit contenir au moins 9 chiffres.',
            'phone.max' => 'Le numéro de téléphone ne doit pas dépasser 20 caractères.',
            'phone.regex' => 'Le numéro de téléphone doit être au format valide (ex: +221771234567 ou 771234567).',

            'role.required' => 'Veuillez sélectionner un rôle.',
            'role.in' => 'Le rôle doit être soit "organizer" soit "participant".',

            'photo.image' => 'Le fichier doit être une image valide.',
            'photo.mimes' => 'L\'image doit être au format jpeg, png, jpg ou gif.',
            'photo.max' => 'La taille de l\'image ne doit pas dépasser 2 Mo.'
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Veuillez corriger les erreurs suivantes :',
                'errors' => $validator->errors()
            ], 422)
        );
    }
}
