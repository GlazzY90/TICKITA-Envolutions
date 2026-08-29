<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/*
Logic:
Returns the small lookup lists needed by support-agent controls.

Structure:
One endpoint provides both organizations and agents, avoiding separate
controllers/endpoints for two simple dropdown datasets.

DSA:
Two database queries are performed.
Each result is alphabetically ordered by MySQL.
For o organizations and a agents, response construction is O(o + a).
*/
class SupportOptionsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->isSupportAgent(),
            403
        );

        $organizations = Organization::query()
            ->orderBy('name')
            ->get([
                'id',
                'name',
            ]);

        $agents = User::query()
            ->where(
                'role',
                UserRole::SUPPORT_AGENT->value
            )
            ->orderBy('name')
            ->get([
                'id',
                'name',
            ]);

        return response()->json([
            'organizations' => $organizations,
            'agents' => $agents,
        ]);
    }
}
