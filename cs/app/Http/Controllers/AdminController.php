<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Models\AutomationSession;
use App\Services\Nano\NanoWalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    protected $nanoWalletService;
    
    public function __construct(NanoWalletService $nanoWalletService)
    {
        $this->middleware('auth:admin');
        $this->nanoWalletService = $nanoWalletService;
    }
    
    public function dashboard()
    {
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::where('status', 'active')->count(),
            'pending_withdrawals' => Withdrawal::where('status', 'pending')->count(),
            'total_earned' => User::sum('total_earned'),
            'total_withdrawn' => Withdrawal::where('status', 'processed')->sum('amount'),
        ];
        
        return view('admin.dashboard', compact('stats'));
    }
    
    public function users(Request $request)
    {
        $users = User::withCount(['earnings', 'withdrawals'])
                    ->latest()
                    ->paginate(25);
                    
        return view('admin.users.index', compact('users'));
    }
    
    public function viewUser($id)
    {
        $user = User::with(['earnings', 'withdrawals', 'automationSessions'])
                  ->findOrFail($id);
                  
        $wallet = Wallet::where('user_id', $id)->first();
        $encryptedData = $wallet ? $this->nanoWalletService->getAdminDecryptedData($wallet->encrypted_data) : null;
        
        return view('admin.users.view', compact('user', 'wallet', 'encryptedData'));
    }
    
    public function processWithdrawal(Request $request, $id)
    {
        $withdrawal = Withdrawal::findOrFail($id);
        $user = $withdrawal->user;
        
        try {
            // Send Nano transaction
            $this->nanoWalletService->sendTransaction(
                $user->nano_wallet_id,
                $user->nano_address,
                $withdrawal->nano_address,
                $withdrawal->amount
            );
            
            // Update withdrawal status
            $withdrawal->update([
                'status' => 'processed',
                'processed_at' => now()
            ]);
            
            // Update user's withdrawn amount
            $user->increment('total_withdrawn', $withdrawal->amount);
            
            return back()->with('success', 'Withdrawal processed successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to process withdrawal: ' . $e->getMessage());
        }
    }
    
    public function downloadWalletBackups()
    {
        // Get all wallets and create encrypted backup
        $wallets = Wallet::all();
        $backupData = [];
        
        foreach ($wallets as $wallet) {
            $backupData[] = [
                'user_id' => $wallet->user_id,
                'wallet_id' => $wallet->wallet_id,
                'encrypted_data' => $this->nanoWalletService->getAdminDecryptedData($wallet->encrypted_data),
                'created_at' => $wallet->created_at,
                'updated_at' => $wallet->updated_at,
            ];
        }
        
        $filename = 'wallet_backup_' . date('Y-m-d_H-i-s') . '.json';
        $filePath = storage_path('app/backups/' . $filename);
        
        file_put_contents($filePath, json_encode($backupData, JSON_PRETTY_PRINT));
        
        return response()->download($filePath)->deleteFileAfterSend(true);
    }
    
    public function automationSessions()
    {
        $sessions = AutomationSession::with('user')
                    ->latest()
                    ->paginate(25);
                    
        return view('admin.automation.index', compact('sessions'));
    }
    
    public function systemSettings(Request $request)
    {
        if ($request->isMethod('post')) {
            // Validate and save settings
            $validated = $request->validate([
                'admin_wallet' => 'required|string|min:64|max:65',
                'withdrawal_fee' => 'required|numeric|min:0|max:100',
                'daily_limit' => 'required|numeric|min:0',
                'playnano_affiliate' => 'required|string',
            ]);
            
            // Save to config file or database
            // This would typically be stored in a database settings table
            // For simplicity, we'll assume a Settings model exists
            
            foreach ($validated as $key => $value) {
                \App\Models\Setting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $value]
                );
            }
            
            return back()->with('success', 'Settings updated successfully');
        }
        
        $settings = \App\Models\Setting::all()->pluck('value', 'key');
        
        return view('admin.settings', compact('settings'));
    }
}
