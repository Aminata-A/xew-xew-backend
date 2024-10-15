<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Autoriser par défaut
    }

    public function rules()
    {
        return [
            // 'name' => 'required|string|max:255',
            // 'date' => 'required|date',
            // 'time' => 'required',
            // 'location' => 'required|string|max:255',
            // 'event_status' => 'required|in:publier,brouillon,archiver,annuler,supprimer',
            // 'description' => 'nullable|string',
            // 'banner' => 'nullable|string',
            // 'ticket_quantity' => 'required|integer|min:1',
            // 'ticket_price' => 'required|numeric|min:0',
            // 'categories' => 'array|exists:categories,id',
            // // 'organizer_id' => 'required|exists:registered_users,id',
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'Le champ "nom" est obligatoire.',
            'name.string' => 'Le champ "nom" doit être une chaîne de caractères.',
            'name.max' => 'Le champ "nom" ne doit pas dépasser 255 caractères.',

            'date.required' => 'Le champ "date" est obligatoire.',
            'date.date' => 'La "date" doit être une date valide.',

            'time.required' => 'Le champ "heure" est obligatoire.',

            'location.required' => 'Le champ "lieu" est obligatoire.',
            'location.string' => 'Le champ "lieu" doit être une chaîne de caractères.',
            'location.max' => 'Le champ "lieu" ne doit pas dépasser 255 caractères.',

            'event_status.required' => 'Le statut de l\'événement est obligatoire.',
            'event_status.in' => 'Le statut de l\'événement doit être l\'un des suivants : publier, brouillon, archiver, annuler, supprimer.',

            'description.string' => 'La description doit être une chaîne de caractères.',

            'banner.string' => 'La bannière doit être une chaîne de caractères.',

            'ticket_quantity.required' => 'Le nombre de tickets est obligatoire.',
            'ticket_quantity.integer' => 'Le nombre de tickets doit être un entier.',
            'ticket_quantity.min' => 'Le nombre de tickets doit être d\'au moins 1.',

            'ticket_price.required' => 'Le prix du ticket est obligatoire.',
            'ticket_price.numeric' => 'Le prix du ticket doit être un nombre.',
            'ticket_price.min' => 'Le prix du ticket doit être au moins 0.',

            'categories.array' => 'Les catégories doivent être un tableau.',
            'categories.exists' => 'Les catégories sélectionnées doivent exister.',
        ];
    }
}
