<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->is_active) {
            return response()->json(['message' => 'Қолжетімсіз.', 'code' => 'user_disabled'], 403);
        }

        $allowed = match ($role) {
            'admin' => $user->isAdmin(),
            'teacher' => $user->isTeacher(),
            default => true,
        };

        if (! $allowed) {
            return response()->json(['message' => 'Бұл бөлім оқытушыға арналған.', 'code' => 'forbidden'], 403);
        }

        return $next($request);
    }
}
