<?php

namespace App\Http\Controllers;

use App\Models\RegisteredUser;
use App\Http\Requests\StoreRegisteredUserRequest;
use App\Http\Requests\UpdateRegisteredUserRequest;

class RegisteredUserController extends Controller
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
    public function store(StoreRegisteredUserRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(RegisteredUser $registeredUser)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(RegisteredUser $registeredUser)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRegisteredUserRequest $request, RegisteredUser $registeredUser)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RegisteredUser $registeredUser)
    {
        //
    }
}
