<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    //
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 404);
        }

        return response()->json([
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

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 404);
        }

        $validator = \Validator::make($request->all(), [
            'name' => 'sometimes|string|max:50',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'phone' => 'sometimes|string|size:11',
            'city' => 'sometimes|string|max:25',
            'image' => 'sometimes|image|max:2048|mimes:jpeg,jpg,png', // Max 2MB
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->fill($request->only(['name', 'email', 'phone', 'city']));
        
        if ($request->hasFile('image')) {
            // delete old image if exists
            if ($user->image) {
                \Storage::disk('public')->delete($user->image);
            }
            // Handle image upload
            $user->image = $request->file('image') ? $request->file('image')->store('profile_images', 'public') : null;
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
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

    public function deleteProfile(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 404);
        }

        // Delete profile image if exists
        if ($user->image) {
            \Storage::disk('public')->delete($user->image);
        }

        // Delete user
        $user->delete();

        return response()->json(['message' => 'Profile deleted successfully'], 200);
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 404);
        }

        $validator = \Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check current password
        if (!\Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Current password is incorrect'], 400);
        }

        // Update to new password
        $user->password = \Hash::make($request->new_password);
        $user->save();

        return response()->json(['message' => 'Password changed successfully'], 200);
    }
}
