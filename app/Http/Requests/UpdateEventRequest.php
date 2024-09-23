<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventRequest extends FormRequest
{
    public function authorize()
    {
        // If needed, you can put authorization logic here
        return true; // Allow for now
    }

    public function rules()
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'date' => 'sometimes|required|date',
            'time' => 'sometimes|required',
            'location' => 'sometimes|required|string|max:255',
            'event_status' => 'sometimes|required|in:publier,brouillon,archiver,annuler,supprimer',
            'description' => 'nullable|string',
            'banner' => 'nullable|string',
            'ticket_quantity' => 'sometimes|required|integer|min:1',
            'ticket_price' => 'sometimes|required|numeric|min:0',
            'categories' => 'sometimes|array|exists:categories,id', // Ensure categories exist
        ];
    }
}
