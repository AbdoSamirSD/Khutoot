<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    //

    public function index(){
        $user = auth('user')->user();
        if(!$user){
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if(!$user->wallet){
            return response()->json(['message' => 'Wallet not found'], 404);
        }

        return response()->json([
            'message' => 'User wallet retrieved successfully',
            'user' => $user->name,
            'wallet' => $user->wallet->only(['id', 'balance'])
        ], 200);
    }

    public function transactionHistory(Request $request){
        $user = auth('user')->user();
        if(!$user){
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if(!$user->wallet){
            return response()->json(['message' => 'Wallet not found'], 404);
        }

        $transactions = $user->wallet->transactions()->with('booking')->orderBy('created_at', 'desc')->get();

        return response()->json([
            'message' => 'Transaction history retrieved successfully',
            'transactions' => $transactions
        ], 200);
    }

    public function addFunds(Request $request){
        $user = auth('user')->user();
        if(!$user){
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validator = \Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|string|in:instapay,vodafone_cash',
            'screenshot' => 'required|image|max:2048|mimes:jpeg,jpg,png', // Max 2MB
            'reference_number' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if(!$user->wallet){
            // Create wallet if not exists
            $user->wallet()->create([
                'user_id' => $user->id,
                'balance' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Handle screenshot upload
        $screenshotPath = $request->file('screenshot') ? $request->file('screenshot')->store('wallet_screenshots', 'public') : null;

        // Here, you would typically create a pending transaction record for admin approval
        $user->wallet->payments()->create([
            'user_id' => $user->id,
            'wallet_user_id' => $user->wallet->id,
            'amount' => $request->amount,
            'payment_method' => $request->payment_method,
            'status' => 'pending',
            'reference_number' => $request->reference_number,
            'screenshot_path' => $screenshotPath,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json([
            'message' => 'Add funds request submitted successfully. Awaiting admin approval.',
            'amount' => $request->amount,
            'status' => 'pending',
            'payment_method' => $request->payment_method,
            'screenshot_url' => asset('storage/' . $screenshotPath),
            'reference_number' => $request->reference_number
        ], 200);
    }
}
