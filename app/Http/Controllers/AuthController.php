<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\CourseRegistrationNotification;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use App\Mail\PasswordResetMail;

class AuthController extends Controller
{

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'role' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Verify role
        if ($user->role !== $request->role) {
            return response()->json([
                'message' => 'Unauthorized role access'
            ], 403);
        }

        // Enforce strict concurrent session restriction: Block if active on another device
        $existingTokens = $user->tokens()->get();
        if ($existingTokens->isNotEmpty() && !$request->force) {
            $currentIp = $request->ip();
            $isActiveOnOtherDevice = false;
            $activeTokenName = null;

            foreach ($existingTokens as $token) {
                // Check if the token was used in the last 30 minutes
                $isRecent = !$token->last_used_at || $token->last_used_at->gt(now()->subMinutes(30));
                
                // If the token name (which contains the IP) doesn't match current IP AND it's recent
                if (!str_contains($token->name, "({$currentIp})") && $isRecent) {
                    $isActiveOnOtherDevice = true;
                    $activeTokenName = $token->name;
                    break;
                }
            }

            if ($isActiveOnOtherDevice) {
                return response()->json([
                    'message' => "Access Denied: This account is currently active on another device ({$activeTokenName}). Please sign out from your other session or confirm to sign out others and enter here.",
                    'action_required' => 'confirm_force_login',
                    'active_device' => $activeTokenName
                ], 423);
            }
        }

        // If we reach here, either force is requested, sessions are stale, or same IP
        $user->tokens()->delete();

        // Get device details for the new session name
        $userAgent = $request->header('User-Agent');
        $ip = $request->ip();
        
        $device = 'Unknown Device';
        if (str_contains($userAgent, 'Windows')) $device = 'Windows PC';
        elseif (str_contains($userAgent, 'Macintosh')) $device = 'Mac';
        elseif (str_contains($userAgent, 'iPhone')) $device = 'iPhone';
        elseif (str_contains($userAgent, 'Android')) $device = 'Android';
        elseif (str_contains($userAgent, 'Linux')) $device = 'Linux';

        $browser = 'Unknown Browser';
        if (str_contains($userAgent, 'Chrome')) $browser = 'Chrome';
        elseif (str_contains($userAgent, 'Firefox')) $browser = 'Firefox';
        elseif (str_contains($userAgent, 'Safari')) $browser = 'Safari';
        elseif (str_contains($userAgent, 'Edge')) $browser = 'Edge';

        $sessionName = "{$device} • {$browser} ({$ip})";

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $user->createToken($sessionName)->plainTextToken
        ]);
    }

    public function getActiveSessions(Request $request)
    {
        $user = $request->user();
        $sessions = $user->tokens()->orderBy('created_at', 'desc')->get()->map(function($token) use ($request) {
            return [
                'id' => $token->id,
                'name' => $token->name,
                'is_current' => $request->user()->currentAccessToken()->id === $token->id,
                'last_used_at' => $token->last_used_at ? $token->last_used_at->diffForHumans() : 'Never',
                'created_at' => $token->created_at->diffForHumans(),
            ];
        });

        return response()->json($sessions);
    }

    public function logoutSession(Request $request, $tokenId)
    {
        $user = $request->user();
        $user->tokens()->where('id', $tokenId)->delete();

        return response()->json(['message' => 'Session terminated successfully']);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:students,email,' . $user->id,
                'bio' => 'nullable|string',
            ]);

            $user->update($validated);

            return response()->json([
                'message' => 'Profile updated successfully',
                'user' => $user->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Profile update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Current password does not match our records.'
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response()->json([
            'message' => 'Password updated successfully'
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            // Silently fail to prevent email enumeration, but frontend can handle it
            return response()->json(['message' => 'If this email exists in our records, we have sent a reset code.'], 200);
        }

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            ['token' => Hash::make($code), 'created_at' => now()]
        );

        Mail::to($request->email)->send(new PasswordResetMail($code));

        return response()->json(['message' => 'Reset code sent to your email.']);
    }

    public function verifyResetCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6'
        ]);

        $record = DB::table('password_reset_tokens')->where('email', $request->email)->first();

        if (!$record || !Hash::check($request->code, $record->token)) {
            return response()->json(['message' => 'Invalid or expired code.'], 422);
        }

        // Check expiry (15 mins)
        if (now()->parse($record->created_at)->addMinutes(15)->isPast()) {
             return response()->json(['message' => 'Code has expired.'], 422);
        }

        return response()->json(['message' => 'Code verified successfully.']);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed'
        ]);

        $record = DB::table('password_reset_tokens')->where('email', $request->email)->first();

        if (!$record || !Hash::check($request->code, $record->token)) {
            return response()->json(['message' => 'Invalid code.'], 422);
        }

        if (now()->parse($record->created_at)->addMinutes(15)->isPast()) {
             return response()->json(['message' => 'Code has expired.'], 422);
        }

        User::where('email', $request->email)->update([
            'password' => Hash::make($request->password)
        ]);

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Password has been reset successfully.']);
    }
}
