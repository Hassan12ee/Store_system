<?php

namespace App\Http\Controllers\Api\Users;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Mail;
use App\Mail\OtpMail;
use Illuminate\Support\Facades\Cache;

class AuthController extends Controller
{

    // Register a New User
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'FristName' => 'required|string|between:2,100',
            'LastName' => 'required|string|between:2,100',
            'email' => 'required|string|email|max:100|unique:users',
            'password' => 'required|string|min:6',
            'Birthday' => 'required|date',
            'Phone' => 'required|numeric|digits_between:7,15',
            'Gender' => 'required|in:Male,Female',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = User::create([
            'FristName' => $request->FristName,
            'LastName' => $request->LastName,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'Birthday' => $request->Birthday,
            'Phone' => $request->Phone,
            'Gender' => $request->Gender,
        ]);

        // ✅ توليد وحفظ الـ OTP
        $otp = rand(100000, 999999);
        Cache::put('otp_' . $user->email, $otp, now()->addMinutes(5));
        Cache::put('otp_sent_recently_' . $user->email, true, now()->addMinute());

        // ✅ إرسال البريد
        Mail::to($user->email)->send(new OtpMail($otp));

        // ✅ توليد توكن JWT
        $token = JWTAuth::fromUser($user);

        return response()->json([
            'message' => 'User registered successfully. OTP sent to email.',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    //verify the Email
    public function verify(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:6',
        ]);

        $cachedOtp = Cache::get('otp_' . $request->email);

        if (!$cachedOtp) {
            return response()->json(['message' => 'OTP expired or not found'], 400);
        }

        if ($request->otp != $cachedOtp) {
            return response()->json(['message' => 'Invalid OTP'], 400);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // ✅ تحديث التحقق
        $user->email_verified_at = now();
        $user->save();

        // حذف الكاش
        Cache::forget('otp_' . $request->email);
        Cache::forget('otp_sent_recently_' . $request->email);

        return response()->json(['message' => 'Email verified successfully']);
    }


    public function resendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->email;

        // منع إعادة الإرسال خلال دقيقة
        if (Cache::has('otp_sent_recently_' . $email)) {
            return response()->json([
                'message' => 'OTP was recently sent. Please wait a minute before requesting again.',
            ], 429); // Too Many Requests
        }

        // توليد كود جديد
        $otp = rand(100000, 999999);

        // حفظ الكود في الكاش
        Cache::put('otp_' . $email, $otp, now()->addMinutes(5));         // مدة صلاحية الكود
        Cache::put('otp_sent_recently_' . $email, true, now()->addMinute()); // مضاد السبام

        // إرسال البريد
        Mail::to($email)->send(new OtpMail($otp));

        return response()->json([
            'message' => 'OTP resent to your email.',
        ]);
    }

    // Login User
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['error' => 'credentials invalid'], 401);
        }
        $user = JWTAuth::user();

        if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
        }


        return $this->respondWithToken($token);
    }

    // Get Authenticated User
    public function me()
    {
        return response()->json(JWTAuth::user());
    }

    // Logout the User
    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());
        return response()->json(['message' => 'Successfully logged out']);
    }

    // Refresh the Token
    public function refresh()
    {
        return $this->respondWithToken(JWTAuth::refresh(JWTAuth::getToken()));
    }

    // Generate a Token Response
    protected function respondWithToken($token)
    {
        return response()->json([
            'user' => JWTAuth::user(),
            'token' => $token,
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
        ]);
    }


    public function sendResetOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $otp = rand(100000, 999999);

        Cache::put('reset_otp_' . $request->email, $otp, now()->addMinutes(5));
        Cache::put('reset_otp_limit_' . $request->email, true, now()->addMinute());

        Mail::to($request->email)->send(new OtpMail($otp));

        return response()->json(['message' => 'Reset OTP sent to your email']);
    }


    public function verifyResetOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|digits:6',
        ]);

        $cachedOtp = Cache::get('reset_otp_' . $request->email);

        if (!$cachedOtp || $cachedOtp != $request->otp) {
            return response()->json(['message' => 'Invalid or expired OTP'], 400);
        }

        // خزّن تأكيد النجاح
        Cache::put('otp_verified_' . $request->email, true, now()->addMinutes(10));

        return response()->json(['message' => 'OTP verified. You can now reset your password']);
    }


    public function resetPasswordWithOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'new_password' => 'required|min:6|confirmed',
        ]);

        if (!Cache::get('otp_verified_' . $request->email)) {
            return response()->json(['message' => 'OTP not verified'], 403);
        }

        $user = \App\Models\User::where('email', $request->email)->first();
        $user->password = Hash::make($request->new_password);
        $user->save();

        Cache::forget('reset_otp_' . $request->email);
        Cache::forget('otp_verified_' . $request->email);
        Cache::forget('reset_otp_limit_' . $request->email);

        return response()->json(['message' => 'Password reset successfully']);
    }

}
