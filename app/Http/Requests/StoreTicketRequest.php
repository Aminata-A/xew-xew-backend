<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'event_id' => 'required|exists:events,id',
            'email' => 'required|email',
            'name' => 'required|string|max:255',
            'quantity' => 'required|integer|min:1',
        ];
    }

    /**
     * Get custom error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'event_id.required' => 'L\'identifiant de l\'événement est requis.',
            'event_id.exists' => 'L\'événement spécifié n\'existe pas.',
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.email' => 'Veuillez fournir une adresse email valide.',
            'name.required' => 'Le nom est obligatoire.',
            'name.string' => 'Le nom doit être une chaîne de caractères.',
            'name.max' => 'Le nom ne peut pas dépasser 255 caractères.',
            'quantity.required' => 'La quantité de billets est requise.',
            'quantity.integer' => 'La quantité doit être un nombre entier.',
            'quantity.min' => 'Vous devez acheter au moins 1 billet.',
        ];
    }
}
