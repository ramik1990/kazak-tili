<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'group_id' => ['required', Rule::exists('groups', 'id')->where('is_active', true)],
            'locale' => ['sometimes', Rule::in(['kk', 'ru'])],
        ], [], $this->attributes());

        $user = User::create([
            ...$data,
            'role' => User::ROLE_STUDENT,
        ]);

        return $this->issueToken($user, $request, 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [], $this->attributes());

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['E-mail немесе құпия сөз қате.']]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages(['email' => ['Тіркелгі әкімшімен өшірілген.']]);
        }

        return $this->issueToken($user, $request);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('group'));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Шығу орындалды.']);
    }

    public function updateProfile(Request $request): UserResource
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'locale' => ['sometimes', Rule::in(['kk', 'ru'])],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'current_password' => ['required_with:password', 'string'],
        ], [], $this->attributes());

        if (! empty($data['password']) && ! Hash::check($data['current_password'] ?? '', $user->password)) {
            throw ValidationException::withMessages(['current_password' => ['Ағымдағы құпия сөз қате.']]);
        }

        $user->fill(array_filter($data, fn ($value, $key) => $key !== 'current_password' && $value !== null, ARRAY_FILTER_USE_BOTH))->save();

        return new UserResource($user->load('group'));
    }

    public function groups(): JsonResponse
    {
        $groups = Group::where('is_active', true)->orderBy('name')->get(['id', 'name', 'speciality']);

        return response()->json(['data' => $groups]);
    }

    private function issueToken(User $user, Request $request, int $status = 200): JsonResponse
    {
        $token = $user->createToken($request->input('device_name', 'web'))->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user->load('group')),
        ], $status);
    }

    private function attributes(): array
    {
        return [
            'name' => 'аты-жөні', 'email' => 'e-mail', 'password' => 'құпия сөз',
            'group_id' => 'топ', 'current_password' => 'ағымдағы құпия сөз',
        ];
    }
}
