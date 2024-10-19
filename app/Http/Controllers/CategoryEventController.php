<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use App\Models\CategoryEvent;
use Illuminate\Support\Facades\DB;

class CategoryEventController extends Controller
{
    /**
    * Display a listing of the resource.
    */
    public function index()
    {
        $category = Category::all('events');
        return response()->json($category, 200);
    }

    /**
    * Show the form for creating a new resource.
    */
    public function create()
    {
        //
    }

    /**
    * Store a newly created resource in storage.
    */
    public function store(Request $request)
    {
        //
    }

    /**
    * Display the specified resource.
    */
    public function show($id)
    {
        // Charger la catégorie avec ses événements
        $category = Category::with('events')->findOrFail($id);

        return response()->json($category);
    }

    /**
    * Show the form for editing the specified resource.
    */
    public function edit(string $id)
    {
        //
    }

    /**
    * Update the specified resource in storage.
    */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
    * Remove the specified resource from storage.
    */
    public function destroy(string $id)
    {
        //
    }

    public function getCategoryEventAssociations()
    {
        // Récupérer toutes les lignes de la table pivot categories_events
        $categoryEvents = DB::table('categories_events')->get();

        return response()->json($categoryEvents);
    }

}
