<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;
use Auth;
use App\Models\Driver;
use Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Authenticate the driver
        $driver = Driver::where('email', $request->email)->first();

        if (!$driver || !Hash::check($request->password, $driver->password)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $token = $driver->createToken('Driver Token')->plainTextToken;

        return response()->json(['token' => $token, 'driver' => $driver], 200);
    }

    public function logout()
    {
        // Invalidate the driver's token
        $driver = Auth::guard('driver')->user();

        if (!$driver) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $driver->tokens()->delete();

        return response()->json(['message' => 'Logged out successfully'], 200);
    }
}

