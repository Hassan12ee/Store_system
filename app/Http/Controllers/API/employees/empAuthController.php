<?php

namespace App\Http\Controllers\Api\employees;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\employee;
use App\Models\Attendance;
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
        $loginField = filter_var($request->input('login'), FILTER_VALIDATE_EMAIL) ? 'email' : 'Phone';
        $credentials = [
            $loginField => $request->input('login'),
            'password' => $request->input('password'),
        ];

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
    public function checkIn(Request $request)
    {
        $employee = auth('employee')->user();
        $today = now()->toDateString();

        // هل الموظف سجل حضور النهاردة؟
        $existing = Attendance::where('employee_id', $employee->id)
                            ->where('date', $today)
                            ->first();

        if ($existing && $existing->check_in) {
            return response()->json(['message' => 'Already checked in today'], 400);
        }

        $officialStart = Carbon::createFromTime(9, 0, 0);
        $checkInTime = now();

        $lateBy = $checkInTime->gt($officialStart)
            ? $checkInTime->diff($officialStart)->format('%H:%I:%S')
            : null;

        $attendance = Attendance::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => $today],
            ['check_in' => $checkInTime->toTimeString(), 'late_by' => $lateBy]
        );

        return response()->json([
            'message' => 'Check-in recorded',
            'attendance' => $attendance
        ]);
    }



    public function checkOut(Request $request)
    {
            $employee = auth('employee')->user();
            $today = now()->toDateString();

            $attendance = Attendance::where('employee_id', $employee->id)
                                    ->where('date', $today)
                                    ->first();

            if (!$attendance || !$attendance->check_in) {
                return response()->json(['message' => 'You must check in first'], 400);
            }

            if ($attendance->check_out) {
                return response()->json(['message' => 'Already checked out today'], 400);
            }

            $checkOutTime = now();
            $checkInTime = Carbon::createFromTimeString($attendance->check_in);

            // 🔻 نهاية الدوام الرسمي
            $officialEnd = Carbon::createFromTime(17, 0, 0);

            // 🕓 فرق الوقت لو خرج بدري
            $leftEarlyBy = $checkOutTime->lt($officialEnd)
                ? $officialEnd->diff($checkOutTime)->format('%H:%I:%S')
                : null;

            // ⏱️ الوقت اللي اشتغله
            $workedHours = $checkInTime->diff($checkOutTime)->format('%H:%I:%S');

            $attendance->update([
                'check_out'      => $checkOutTime->toTimeString(),
                'left_early_by'  => $leftEarlyBy,
                'worked_hours'   => $workedHours,
            ]);

            return response()->json([
                'message' => 'Check-out recorded',
                'attendance' => $attendance,
            ]);
    }



    // get attendances by employee, date range, etc.
    public function index(Request $request)
    {
        $query = Attendance::query();

        if ($request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->start_date && $request->end_date) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        return response()->json($query->with('employee')->latest()->paginate(15));
    }

    public function monthlyReport(Request $request)
    {
        $employee = auth('employee')->user();

        $month = $request->input('month', now()->format('m')); // مثل: 07
        $year  = $request->input('year', now()->format('Y'));

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate   = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        $report = Attendance::where('employee_id', $employee->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->select([
                DB::raw('COUNT(*) as attendance_days'),
                DB::raw('SEC_TO_TIME(SUM(TIME_TO_SEC(worked_hours))) as total_worked'),
                DB::raw('SEC_TO_TIME(SUM(TIME_TO_SEC(late_by))) as total_late'),
                DB::raw('SEC_TO_TIME(SUM(TIME_TO_SEC(left_early_by))) as total_left_early'),
            ])
            ->first();

        return response()->json([
            'employee' => [
                'id' => $employee->id,
                'FristName' => $employee->FristName,
                'LastName' => $employee->LastName,
            ],
            'month' => $month,
            'year' => $year,
            'attendance_days' => $report->attendance_days,
            'total_worked_hours' => $report->total_worked ?? '00:00:00',
            'total_late' => $report->total_late ?? '00:00:00',
            'total_left_early' => $report->total_left_early ?? '00:00:00',
        ]);

    }

}

