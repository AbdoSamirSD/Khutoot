<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/user/profile",
     *     summary="Get user's profile",
     *     tags={"User Profile"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation"
     *     ),
     *    @OA\Response(
     *        response=404,
     *       description="Unauthenticated"
     *     )
     * )
    */
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

    /**
     * @OA\Put(
     *     path="/api/user/profile",
     *     summary="Update user's profile",
     *     tags={"User Profile"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="name@gmail.com"),
     *             @OA\Property(property="phone", type="string", example="01234567890"),
     *             @OA\Property(property="city", type="string", example="Cairo"),
     *             @OA\Property(property="image", type="string", format="binary", description="Profile image file"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile updated successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
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

    /**
     * @OA\Delete(
     *     path="/api/user/profile",
     *     summary="Delete user's profile",
     *     tags={"User Profile"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Profile deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Unauthenticated"
     *     )
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/api/user/profile/change-password",
     *     summary="Change user's password",
     *     tags={"User Profile"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="current_password", type="string", example="currentPassword123"),
     *             @OA\Property(property="new_password", type="string", example="newPassword123"),
     *             @OA\Property(property="new_password_confirmation", type="string", example="newPassword123"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password changed successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Current password is incorrect"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
    */

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
