<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * إنشاء حساب جديد.
     */
    public function register(Request $request): JsonResponse
{
    $validated = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        'password' => ['required', 'string', 'min:6', 'confirmed'],
        'role' => ['required', 'string', 'in:member,admin'], 
        'profile_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
    ]);

    $profileImagePath = null;

    if ($request->hasFile('profile_image')) {
        $profileImagePath = $request
            ->file('profile_image')
            ->store('profile-images', 'public');
    }

    $user = new User();

    $user->name = $validated['name'];
    $user->email = strtolower($validated['email']);
    $user->password = Hash::make($validated['password']);
    $user->role = $validated['role']; // يأخذ القيمة من المستخدم
    $user->profile_image = $profileImagePath;
    $user->email_verified_at = now();
    $user->save();

    $token = $user->createToken('flutter-app')->plainTextToken;

    return response()->json([
        'success' => true,
        'message' => 'تم إنشاء الحساب بنجاح.',
        'data' => [
            'user' => $this->formatUser($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ],
    ], 201);
}


    /**
     * تسجيل الدخول.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => [
                'required',
                'email',
            ],

            'password' => [
                'required',
                'string',
            ],

            'device_name' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $user = User::where(
            'email',
            strtolower($validated['email'])
        )->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [
                    'البريد الإلكتروني أو كلمة المرور غير صحيحة.',
                ],
            ]);
        }

        $deviceName = $validated['device_name'] ?? 'flutter-app';

        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الدخول بنجاح.',
            'data' => [
                'user' => $this->formatUser($user),
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    /**
     * معلومات المستخدم المسجل حاليًا.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $this->formatUser($request->user()),
            ],
        ]);
    }

    /**
     * تسجيل الخروج من الجهاز الحالي.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الخروج بنجاح.',
        ]);
    }

    /**
     * تسجيل الخروج من جميع الأجهزة.
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الخروج من جميع الأجهزة.',
        ]);
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'profile_image' => $user->profile_image
                ? asset('storage/' . $user->profile_image)
                : null,
            'created_at' => $user->created_at,
        ];
    }
}
