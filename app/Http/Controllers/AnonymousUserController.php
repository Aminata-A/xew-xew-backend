<?php

namespace App\Http\Controllers;

use App\Models\AnonymousUser;
use App\Http\Requests\StoreAnonymousUserRequest;
use App\Http\Requests\UpdateAnonymousUserRequest;

class AnonymousUserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
    public function store(StoreAnonymousUserRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(AnonymousUser $anonymousUser)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AnonymousUser $anonymousUser)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAnonymousUserRequest $request, AnonymousUser $anonymousUser)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AnonymousUser $anonymousUser)
    {
        //
    }
}
