<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        // Validate the request data
        $validator = \Validator::make($request->all(), [
            'name' => 'required|string|max:50',
            'email' => 'required|string|email|max:70|unique:users',
            'phone' => 'required|string|size:11',
            'city' => 'required|string|max:25',
            'password' => 'required|string|min:8|confirmed',
            'profile_image' => 'nullable|image|mimes:jpeg,jpg,png'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $image_path = null;

        if($request->hasFile('profile_image')){
            $image_path = $request->file('profile_image')->store('profile_images', 'public');
        }

        // Create the user
        $user = User::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'city' => $request->city,
            'password' => \Hash::make($request->password),
            'image' => $image_path ? $image_path : null
        ]);

        // Generate a token for the user
        $token = $user->createToken('auth_token')->plainTextToken;

        // Return the user and token in the response
        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'city' => $user->city,
                'image' => $user->image ? asset('storage/' . $user->image) : null,
            ],
        ], 201);
    }

    public function login(Request $request)
    {
        // Validate the request data
        $validator = \Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Attempt to find the user
        $user = User::where('email', $request->email)->first();

        if (!$user || !\Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Generate a token for the user
        $token = $user->createToken('auth_token')->plainTextToken;

        // Return the user and token in the response
        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'city' => $user->city,
                'image' => $user->image ? asset('storage/' . $user->image) : null,
            ],
        ], 200);
    }

    public function logout(Request $request)
    {
        // Revoke the token that was used to authenticate the current request
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully'], 200);
    }

    public function forgotPassword(Request $request)
    {
        // Validate the request data
        $validator = \Validator::make($request->all(), [
            'email' => 'required|string|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Attempt to find the user
        $user = User::where('email', $request->email)->first();

        // Here you would typically send a password reset email.
        // For simplicity, we'll just return a success message.

        return response()->json(['message' => 'Password reset link sent to your email'], 200);
    }
}
