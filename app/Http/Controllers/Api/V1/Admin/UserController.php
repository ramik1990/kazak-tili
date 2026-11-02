<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = trim((string) $request->query('search'));

        $users = User::query()
            ->with('group')
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->query('role')))
            ->when($request->filled('group_id'), fn ($q) => $q->where('group_id', $request->query('group_id')))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('role')
            ->orderBy('name')
            ->paginate(min(max((int) $request->query('per_page', 50), 1), 200))
            ->withQueryString();

        return UserResource::collection($users);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules(), [], $this->attributes());
        $user = User::create($data);

        return (new UserResource($user->load('group')))->response()->setStatusCode(201);
    }

    public function update(Request $request, User $user): UserResource
    {
        $data = $request->validate($this->rules($user), [], $this->attributes());

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->fill($data)->save();

        return new UserResource($user->load('group'));
    }

    public function destroy(User $user): Response
    {
        $user->delete();

        return response()->noContent();
    }

    public function groups(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $groups = Group::with('teacher:id,name')->withCount('students')->orderBy('name')->get();

        return response()->json(['data' => $groups]);
    }

    public function storeGroup(Request $request): JsonResponse
    {
        $group = Group::create($request->validate($this->groupRules(), [], $this->attributes()));

        return response()->json(['data' => $group->loadCount('students')], 201);
    }

    public function updateGroup(Request $request, Group $group): JsonResponse
    {
        $group->fill($request->validate($this->groupRules(), [], $this->attributes()))->save();

        return response()->json(['data' => $group->loadCount('students')]);
    }

    public function destroyGroup(Group $group): Response
    {
        $group->delete();

        return response()->noContent();
    }

    private function rules(?User $user = null): array
    {
        return [
            'name' => [$user ? 'sometimes' : 'required', 'string', 'max:255'],
            'email' => [$user ? 'sometimes' : 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', Password::min(8)],
            'role' => ['sometimes', Rule::in([User::ROLE_STUDENT, User::ROLE_TEACHER, User::ROLE_ADMIN])],
            'group_id' => ['nullable', Rule::exists('groups', 'id')],
            'locale' => ['sometimes', Rule::in(['kk', 'ru'])],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function groupRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'speciality' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:1', 'max:6'],
            'teacher_id' => ['nullable', Rule::exists('users', 'id')],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function attributes(): array
    {
        return ['name' => 'аты', 'email' => 'e-mail', 'password' => 'құпия сөз', 'role' => 'рөлі', 'group_id' => 'тобы'];
    }
}
