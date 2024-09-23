<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    // Display a listing of categories
    public function index()
    {
        $categories = Category::all();
        return response()->json($categories, 200);
    }

    // Show the form for creating a new category
    public function create()
    {
        // Not needed for API, generally used for frontend forms
    }

    // Store a newly created category in storage
    public function store(Request $request)
    {
        $request->validate([
            'label' => 'required|string|max:255',
        ]);

        $categorie = Category::create([
            'label' => $request->label,
        ]);

        return response()->json($categorie, 201);
    }

    // Display the specified category
    public function show($id)
    {
        $categorie = Category::find($id);

        if (!$categorie) {
            return response()->json(['error' => "Categorie n'existe pas"], 404);
        }

        return response()->json($categorie, 200);
    }

    // Show the form for editing the specified category
    public function edit($id)
    {
        // Not needed for API, generally used for frontend forms
    }

    // Update the specified category in storage
    public function update(Request $request, $id)
    {
        $categorie = Category::find($id);

        if (!$categorie) {
            return response()->json(['error' => "Categorie n'existe pas"], 404);
        }

        $request->validate([
            'label' => 'required|string|max:255',
        ]);

        $categorie->label = $request->label;
        $categorie->save();

        return response()->json(['message' => 'Categorie mise à jour avec succès', 'category' => $categorie], 200);
    }

    // Remove the specified category from storage (soft delete can also be applied here)
    public function destroy($id)
    {
        $categorie = Category::find($id);

        if (!$categorie) {
            return response()->json(['error' => "Categorie n'existe pas"], 404);
        }

        $categorie->delete();

        return response()->json(['message' => 'Categorie supprimer avec succès'], 200);
    }
}
