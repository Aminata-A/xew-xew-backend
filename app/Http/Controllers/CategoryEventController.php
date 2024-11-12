<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CategoryEventController extends Controller
{
    /**
    * Afficher la liste des catégories avec leurs événements.
    */
    public function index()
    {
        // Récupérer toutes les catégories avec leurs événements associés
        $categories = Category::with('events')->get();
        return response()->json($categories, 200);
    }

    /**
    * Formulaire pour créer une nouvelle ressource (non utilisé dans une API).
    */
    public function create()
    {
        //
    }

    /**
    * Stocker une nouvelle catégorie avec ses événements associés.
    */
    public function store(Request $request)
    {
        // Valider les données de la requête
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'events' => 'required|array', // Les événements doivent être un tableau
            'events.*' => 'exists:events,id', // Chaque événement doit exister dans la table 'events'
        ], [
            'name.required' => 'Le nom de la catégorie est requis.',
            'name.string' => 'Le nom doit être une chaîne de caractères.',
            'name.max' => 'Le nom ne peut pas dépasser 255 caractères.',
            'events.required' => 'Les événements sont requis.',
            'events.array' => 'Les événements doivent être un tableau.',
            'events.*.exists' => 'Chaque événement doit exister dans la base de données.',
        ]);

        // Si la validation échoue, renvoyer les erreurs
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Créer la catégorie
        $category = Category::create(['name' => $request->input('name')]);

        // Associer les événements à la catégorie
        $category->events()->sync($request->input('events'));

        return response()->json(['message' => 'Catégorie et événements associés créés avec succès.', 'category' => $category], 201);
    }

    /**
    * Afficher une catégorie spécifique avec ses événements.
    */
    public function show($id)
    {
        // Charger la catégorie avec ses événements
        $category = Category::with('events')->findOrFail($id);

        return response()->json($category, 200);
    }

    /**
    * Formulaire pour éditer une ressource (non utilisé dans une API).
    */
    public function edit($id)
    {
        //
    }

    /**
    * Mettre à jour une catégorie avec ses événements.
    */
    public function update(Request $request, $id)
    {
        // Valider les données de la requête
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'events' => 'required|array', // Les événements doivent être un tableau
            'events.*' => 'exists:events,id', // Chaque événement doit exister dans la table 'events'
        ], [
            'name.required' => 'Le nom de la catégorie est requis.',
            'name.string' => 'Le nom doit être une chaîne de caractères.',
            'name.max' => 'Le nom ne peut pas dépasser 255 caractères.',
            'events.required' => 'Les événements sont requis.',
            'events.array' => 'Les événements doivent être un tableau.',
            'events.*.exists' => 'Chaque événement doit exister dans la base de données.',
        ]);

        // Si la validation échoue, renvoyer les erreurs
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Trouver la catégorie
        $category = Category::findOrFail($id);

        // Mettre à jour les informations de la catégorie
        $category->update(['name' => $request->input('name')]);

        // Mettre à jour les événements associés
        $category->events()->sync($request->input('events'));

        return response()->json(['message' => 'Catégorie mise à jour avec succès.', 'category' => $category], 200);
    }

    /**
    * Supprimer une catégorie spécifique (peut inclure une suppression douce).
    */
    public function destroy($id)
    {
        // Rechercher la catégorie par ID
        $category = Category::findOrFail($id);

        // Supprimer la catégorie
        $category->delete();

        return response()->json(['message' => 'Catégorie supprimée avec succès.'], 200);
    }

    /**
    * Récupérer toutes les associations entre catégories et événements.
    */
    public function getCategoryEventAssociations()
    {
        // Récupérer toutes les lignes de la table pivot categories_events
        $categoryEvents = DB::table('categories_events')->get();

        return response()->json($categoryEvents, 200);
    }
}
