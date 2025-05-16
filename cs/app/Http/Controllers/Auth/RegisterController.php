<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Nano\NanoWalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Auth\Events\Registered;
use App\Rules\Captcha;

class RegisterController extends Controller
{
    protected $nanoWalletService;

    public function __construct(NanoWalletService $nanoWalletService)
    {
        $this->nanoWalletService = $nanoWalletService;
    }

    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'g-recaptcha-response' => ['required', new Captcha],
        ]);
    }

    protected function create(array $data)
    {
        // Generate referral code
        $referralCode = $this->generateReferralCode();
        
        // Create Nano wallet for user
        $walletData = $this->nanoWalletService->createWallet();
        
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'referral_code' => $referralCode,
            'referred_by' => $data['referral'] ?? null,
            'nano_address' => $walletData['address'],
            'nano_wallet_id' => $walletData['wallet_id'],
        ]);
        
        // Store encrypted wallet data
        $this->nanoWalletService->storeEncryptedWallet($user->id, $walletData);
        
        return $user;
    }

    private function generateReferralCode()
    {
        do {
            $code = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8);
        } while (User::where('referral_code', $code)->exists());
        
        return $code;
    }
}
