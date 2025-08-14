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
use Illuminate\Support\Collection;


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


    // Login Employee
    public function login(Request $request)
    {
        $loginField = filter_var($request->input('login'), FILTER_VALIDATE_EMAIL) ? 'email' : 'Phone';
        $credentials = [
            $loginField => $request->input('login'),
            'password' => $request->input('password'),
        ];

        if (!$token = Auth::attempt($credentials)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }// supporter,Super Admin ,Moderator, warehouse worker, admin
        if (!Auth::user()->hasAnyRole(['Moderator', 'supporter', 'Super Admin', 'warehouse worker', 'admin'])) {
    return response()->json(['error' => 'Invalid credentials'], 401);
}
        return $this->respondWithToken($token);
    }

    // Get Authenticated Employee
    public function me()
    {
        $employee = Auth::user();


        return response()->json([
            'employee' => $employee,
            'roles' => $employee->getRoleNames(),
            'permissions' => $employee->getAllPermissions(),
        ]);
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
        $employee = Auth::user();


        return response()->json([
            'user' => $employee,
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
            'roles' => $employee->getRoleNames(),
            'permissions' => $employee->getAllPermissions()->pluck('name'),

        ]);
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

