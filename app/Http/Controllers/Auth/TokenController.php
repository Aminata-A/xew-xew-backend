<?php

namespace App\Http\Controllers\Auth;

use Tymon\JWTAuth\Token;
use Illuminate\Http\Request;

use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\Controller;
use Database\Factories\PayloadFactory;

class TokenController extends Controller
{
    protected $payloadFactory;

    public function __construct(PayloadFactory $payloadFactory)
    {
        $this->payloadFactory = $payloadFactory;
    }

    public function createCustomToken(array $payload)
    {
        
        // Use PayloadFactory to create the payload
        $payload = $this->payloadFactory->make($payload);

        // Encode the payload into a token
        $token = JWTAuth::encode($payload);

        return (string)$token;
    }
}
