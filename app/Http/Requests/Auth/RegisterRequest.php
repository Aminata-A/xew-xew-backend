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
        $rules = [
            'name' => 'required|string|max:255',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'required|string|max:20',
            'role' => 'required|string|in:organizer,user',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'organization_name' => 'required_if:role,organizer|string|max:255',
            'organization_type' => 'required_if:role,organizer|string|max:255',
            'event_types' => 'required_if:role,organizer|array',
            'event_types.*' => 'required|integer|exists:categories,id'
        ];

        return $rules;
    }

    public function messages()
    {
        $messages = [
            'name.required' => 'Le nom est requis',
            'name.string' => 'Le nom doit être une chaîne de caractères',
            'name.max' => 'Le nom ne peut pas dépasser 255 caractères',
            'phone.required' => 'Le numéro de téléphone est requis',
            'phone.string' => 'Le numéro de téléphone doit être une chaîne de caractères',
            'phone.max' => 'Le numéro de téléphone ne peut pas dépasser 20 caractères',
            'role.required' => 'Le rôle est requis',
            'role.string' => 'Le rôle doit être une chaîne de caractères',
            'role.in' => 'Le rôle doit être soit "organizer" soit "user"',
            'password.required' => 'Le mot de passe est requis',
            'password.string' => 'Le mot de passe doit être une chaîne de caractères',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas',
            'organization_name.required_if' => 'Le nom de l\'organisation est requis pour les organisateurs',
            'organization_name.string' => 'Le nom de l\'organisation doit être une chaîne de caractères',
            'organization_name.max' => 'Le nom de l\'organisation ne peut pas dépasser 255 caractères',
            'organization_type.required_if' => 'Le type d\'organisation est requis pour les organisateurs',
            'organization_type.string' => 'Le type d\'organisation doit être une chaîne de caractères',
            'organization_type.max' => 'Le type d\'organisation ne peut pas dépasser 255 caractères',
            'event_types.required_if' => 'Les types d\'événements sont requis pour les organisateurs',
            'event_types.array' => 'Les types d\'événements doivent être un tableau',
            'event_types.*.required' => 'Chaque type d\'événement est requis',
            'event_types.*.integer' => 'Chaque type d\'événement doit être un nombre',
            'event_types.*.exists' => 'Le type d\'événement sélectionné n\'existe pas',
            'photo.image' => 'Le fichier doit être une image valide.',
            'photo.mimes' => 'L\'image doit être au format jpeg, png, jpg ou gif.',
            'photo.max' => 'La taille de l\'image ne doit pas dépasser 2 Mo.'
        ];

        return $messages;
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
