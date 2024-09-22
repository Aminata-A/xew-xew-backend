<?php

namespace App\Http\Controllers;
use App\Models\User;
use Tymon\JWTAuth\JWT;
use App\Models\RegisteredUser;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\StoreRegisteredUserRequest;
use App\Http\Requests\UpdateRegisteredUserRequest;

class RegisteredUserController extends Controller
{
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
    public function store(Request $request)
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
