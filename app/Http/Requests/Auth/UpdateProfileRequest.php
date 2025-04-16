<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateProfileRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'password' => 'sometimes|string|min:8',
            'role' => 'sometimes|string|in:organizer,user',
            'status' => 'sometimes|string|in:active,inactive',
            'balance' => 'sometimes|numeric|min:0',
            'photo' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
            'organization_name' => 'sometimes|string|max:255',
            'organization_type' => 'sometimes|string|max:255',
            'event_types' => 'sometimes|array',
            'event_types.*' => 'sometimes|integer|exists:categories,id'
        ];
    }

    public function messages()
    {
        return [
            'name.string' => 'Le nom doit être une chaîne de caractères',
            'name.max' => 'Le nom ne peut pas dépasser 255 caractères',
            'phone.string' => 'Le numéro de téléphone doit être une chaîne de caractères',
            'phone.max' => 'Le numéro de téléphone ne peut pas dépasser 20 caractères',
            'password.string' => 'Le mot de passe doit être une chaîne de caractères',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères',
            'role.string' => 'Le rôle doit être une chaîne de caractères',
            'role.in' => 'Le rôle doit être soit "organizer" soit "user"',
            'status.string' => 'Le statut doit être une chaîne de caractères',
            'status.in' => 'Le statut doit être soit "active" soit "inactive"',
            'balance.numeric' => 'Le solde doit être un nombre',
            'balance.min' => 'Le solde ne peut pas être négatif',
            'photo.image' => 'Le fichier doit être une image valide',
            'photo.mimes' => 'L\'image doit être au format jpeg, png, jpg ou gif',
            'photo.max' => 'La taille de l\'image ne doit pas dépasser 2 Mo',
            'organization_name.string' => 'Le nom de l\'organisation doit être une chaîne de caractères',
            'organization_name.max' => 'Le nom de l\'organisation ne peut pas dépasser 255 caractères',
            'organization_type.string' => 'Le type d\'organisation doit être une chaîne de caractères',
            'organization_type.max' => 'Le type d\'organisation ne peut pas dépasser 255 caractères',
            'event_types.array' => 'Les types d\'événements doivent être un tableau',
            'event_types.*.integer' => 'Chaque type d\'événement doit être un nombre',
            'event_types.*.exists' => 'Le type d\'événement sélectionné n\'existe pas'
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
