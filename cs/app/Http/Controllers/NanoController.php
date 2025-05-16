<?php

namespace App\Http\Controllers;

use App\Services\Nano\NanoWalletService;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Auth;

class NanoController extends Controller
{
    protected $nanoService;
    
    public function __construct(NanoWalletService $nanoService)
    {
        $this->nanoService = $nanoService;
    }
    
    public function getBalance(Request $request)
    {
        $user = Auth::user();
        $response = $this->nanoService->getAccountBalance($user->nano_address);
        
        return response()->json([
            'balance' => $response['balance'] ?? 0,
            'pending' => $response['pending'] ?? 0
        ]);
    }
    
    public function requestWithdrawal(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.000001',
            'address' => 'required|string|min:64|max:65'
        ]);
        
        $user = Auth::user();
        $amount = $request->amount;
        
        // Check if user has sufficient balance
        if ($user->total_earned < $amount) {
            return back()->with('error', 'Insufficient balance');
        }
        
        // Create withdrawal request
        $withdrawal = $user->withdrawals()->create([
            'amount' => $amount,
            'nano_address' => $request->address,
            'status' => 'pending'
        ]);
        
        return back()->with('success', 'Withdrawal request submitted');
    }
    
    public function getWalletInfo(Request $request)
    {
        $user = Auth::user();
        $wallet = Wallet::where('user_id', $user->id)->first();
        
        if (!$wallet) {
            return response()->json(['error' => 'Wallet not found'], 404);
        }
        
        return response()->json([
            'address' => $user->nano_address,
            'wallet_id' => $wallet->wallet_id
        ]);
    }
}
