<?php

namespace App\Http\Controllers\Api;

use App\Models\employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use App\Mail\OtpMail;
class empAuthController extends Controller
{
    // Register a New Employee
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'FristName' => 'required|string|between:2,100',
            'LastName' => 'required|string|between:2,100',
            'email' => 'required|string|email|max:100|unique:employees,email',
            'password' => 'required|string|min:6',
            'Birthday' => 'required|date',
            'Phone' => 'required|numeric|digits_between:7,15',
            'Gender' => 'required|in:Male,Female',
            'roles' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $employee = employee::create([
            'FristName' => $request->FristName,
            'LastName' => $request->LastName,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'Birthday' => $request->Birthday,
            'Phone' => $request->Phone,
            'Gender' => $request->Gender,
            'roles' => $request->roles,
        ]);

        // ✅ توليد وحفظ الـ OTP
        $otp = rand(100000, 999999);
        Cache::put('otp_' . $employee->email, $otp, now()->addMinutes(5));
        Cache::put('otp_sent_recently_' . $employee->email, true, now()->addMinute());

        // ✅ إرسال البريد
        Mail::to($employee->email)->send(new OtpMail($otp));


        return response()->json([
            'message' => 'Employee registered successfully. OTP sent to email.',
            'employee' => $employee,

        ], 201);
    }
    public function verify(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:6',
        ]);

        $cachedOtp = Cache::get('emp_otp_' . $request->email);

        if (!$cachedOtp) {
            return response()->json(['message' => 'OTP expired or not found'], 400);
        }

        if ($request->otp != $cachedOtp) {
            return response()->json(['message' => 'Invalid OTP'], 400);
        }

        $employee = Employee::where('email', $request->email)->first();
        if (!$employee) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $employee->email_verified_at = now();
        $employee->save();

        Cache::forget('emp_otp_' . $request->email);
        Cache::forget('emp_otp_limit_' . $request->email);

        // ✅ تسجيل دخول تلقائي بعد التفعيل
        $token = JWTAuth::fromUser($employee);

        return response()->json([
            'message' => 'Email verified and login successful',
            'employee' => $employee,
            'token' => $token,
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
        ]);
    }

    public function resendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:employees,email',
        ]);

        // تأكد ما بيكرر الإرسال كل ثانية
        if (Cache::get('emp_otp_limit_' . $request->email)) {
            return response()->json(['message' => 'Please wait before requesting another OTP'], 429);
        }

        // توليد كود جديد
        $otp = rand(100000, 999999);
        Cache::put('emp_otp_' . $request->email, $otp, now()->addMinutes(5));
        Cache::put('emp_otp_limit_' . $request->email, true, now()->addMinute());

        // إرسال الإيميل
        Mail::to($request->email)->send(new OtpMail($otp));

        return response()->json(['message' => 'OTP resent successfully']);
    }
    // Login Employee
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (!$token = Auth::guard('employee')->attempt($credentials)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        return $this->respondWithToken($token);
    }

    // Get Authenticated Employee
    public function me()
    {
        return response()->json(Auth::guard('employee')->user());
    }

    // Logout the Employee
    public function logout()
    {
        Auth::guard('employee')->logout();
        return response()->json(['message' => 'Successfully logged out']);
    }

    // Refresh the Token

        public function refresh()
        {
            $newToken = JWTAuth::setToken(JWTAuth::getToken())->refresh();

            return $this->respondWithToken($newToken);

        }

    // Token Response Structure
    protected function respondWithToken($token)
    {
        return response()->json([
            'employee' => Auth::guard('employee')->user(),
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
        ]);
    }
    public function sendResetOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:employees,email',
        ]);

        $otp = rand(100000, 999999);

        Cache::put('emp_reset_otp_' . $request->email, $otp, now()->addMinutes(5));
        Cache::put('emp_reset_otp_limit_' . $request->email, true, now()->addMinute());

        Mail::to($request->email)->send(new OtpMail($otp));

        return response()->json(['message' => 'Reset OTP sent to your email']);
    }

    public function verifyResetOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:employees,email',
            'otp' => 'required|digits:6',
        ]);

        $cachedOtp = Cache::get('emp_reset_otp_' . $request->email);

        if (!$cachedOtp || $cachedOtp != $request->otp) {
            return response()->json(['message' => 'Invalid or expired OTP'], 400);
        }

        Cache::put('emp_otp_verified_' . $request->email, true, now()->addMinutes(10));

        return response()->json(['message' => 'OTP verified. You can now reset your password']);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:employees,email',
            'new_password' => 'required|min:6|confirmed',
        ]);

        if (!Cache::get('emp_otp_verified_' . $request->email)) {
            return response()->json(['message' => 'OTP not verified'], 403);
        }

        $employee = Employee::where('email', $request->email)->first();
        $employee->password = Hash::make($request->new_password);
        $employee->save();

        Cache::forget('emp_reset_otp_' . $request->email);
        Cache::forget('emp_otp_verified_' . $request->email);
        Cache::forget('emp_reset_otp_limit_' . $request->email);

        return response()->json(['message' => 'Password reset successfully']);
    }
}

