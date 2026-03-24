<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckActiveAssignment
{
    public function handle(Request $request, Closure $next, string ...$allowedRoleSlugs): Response
    {
        $employee = $request->user();

        if (!$employee instanceof Employee) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        // The active assignment ID is sent by the frontend as a header
        $assignmentId = (int) $request->header('X-Active-Assignment');

        if (!$assignmentId || !$employee->hasAssignment($assignmentId)) {
            return response()->json(['success' => false, 'message' => 'No active assignment context'], 403);
        }

        // Load the full assignment and attach to request for downstream use
        $assignment = $employee->activeAssignments()
            ->with(['department', 'role.permissions'])
            ->find($assignmentId);

        $request->merge(['_active_assignment' => $assignment]);

        // If specific role slugs are required (e.g. middleware('assignment:hr-manager'))
        if (!empty($allowedRoleSlugs) && !in_array($assignment->role->slug, $allowedRoleSlugs, true)) {
            return response()->json(['success' => false, 'message' => 'Insufficient role for this resource'], 403);
        }

        return $next($request);
    }
}
