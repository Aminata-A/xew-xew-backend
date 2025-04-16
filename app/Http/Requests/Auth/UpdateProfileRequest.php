<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\RegisteredUser;

class UpdateProfileRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|regex:/^[0-9]{9,20}$/',
            'photo' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
            'password' => 'sometimes|required|string|min:8',
            'status' => 'sometimes|required|in:active,inactive',
            'balance' => 'sometimes|required|numeric|min:0',
            'organization_name' => 'sometimes|required_if:role,organizer|string|max:255',
            'organization_type' => 'sometimes|required_if:role,organizer|string|max:255',
            'event_types' => 'sometimes|required_if:role,organizer|array',
            'event_types.*' => 'exists:categories,id'
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom est requis',
            'name.string' => 'Le nom doit être une chaîne de caractères',
            'name.max' => 'Le nom ne peut pas dépasser 255 caractères',
            'phone.required' => 'Le numéro de téléphone est requis',
            'phone.regex' => 'Le numéro de téléphone doit être un numéro valide (9 à 20 chiffres)',
            'photo.image' => 'Le fichier doit être une image',
            'photo.mimes' => 'L\'image doit être au format jpeg, png, jpg ou gif',
            'photo.max' => 'L\'image ne peut pas dépasser 2 Mo',
            'password.required' => 'Le mot de passe est requis',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères',
            'status.required' => 'Le statut est requis',
            'status.in' => 'Le statut doit être active ou inactive',
            'balance.required' => 'Le solde est requis',
            'balance.numeric' => 'Le solde doit être un nombre',
            'balance.min' => 'Le solde ne peut pas être négatif',
            'organization_name.required_if' => 'Le nom de l\'organisation est requis pour les organisateurs',
            'organization_type.required_if' => 'Le type d\'organisation est requis pour les organisateurs',
            'event_types.required_if' => 'Les types d\'événements sont requis pour les organisateurs',
            'event_types.array' => 'Les types d\'événements doivent être un tableau',
            'event_types.*.exists' => 'Un ou plusieurs types d\'événements sélectionnés sont invalides'
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
