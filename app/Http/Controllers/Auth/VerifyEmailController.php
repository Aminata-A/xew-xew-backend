<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Mail\SendCodeEmail;
use Illuminate\Http\Request;
use App\Models\RegisteredUser;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;

class VerifyEmailController extends Controller
{
    public function verify(Request $request)
    {
        $id = null;
        // Retrieve email from the request
        $email = $request->input(key: 'email');
        // dd($email);

        // Check if email is provided
        if (!$email) {
            return response()->json(['error' => 'Email is required'], 400);
        }

        // Validate the email using regex
        $emailRegex = "/^[a-zA-Z0-9.+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/";

        if (!preg_match($emailRegex, $email)) {
            return response()->json(['error' => 'Invalid email format'], 400);
        }

        // Check if the user exists
        $user = User::where('email', $email)->first();

        if ($user) {
            // Check if the userable relationship is a RegisteredUser
            if ($user->userable instanceof RegisteredUser) {
                return response()->json(['message' => 'User already exists as RegisteredUser'], 200);
            }
            $id = $user->id;
        }

        // Generate a 6-digit code
        $code = rand(100000, 999999);

        // Store the code in Redis with an expiration time (e.g., 10 minutes)
        Redis::set("verification_code:{$email}", $code, 'EX', 600); // 600 seconds = 10 minutes

        // Send the code to the provided email
        Mail::to($email)->send(mailable: new SendCodeEmail($code));

        return response()->json(['message' => 'Code sent to the provided email address', 'id' => $id],200);

    }


}
