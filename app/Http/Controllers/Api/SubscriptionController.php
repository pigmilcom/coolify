<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentHistory;
use App\Models\Plan;
use App\Models\Team;
use App\Models\TeamPlan;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class SubscriptionController extends Controller
{
    /**
     * Create a new user.
     *
     * POST /api/v1/subscription/users
     *
     * Body: { name, email, password, role? }
     */
    public function createUser(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['sometimes', 'string', 'in:owner,admin,member'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => strtolower($request->email),
            'password' => Hash::make($request->password),
        ]);

        $user->markEmailAsVerified();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ], 201);
    }

    /**
     * Create a new team and optionally assign an owner.
     *
     * POST /api/v1/subscription/teams
     *
     * Body: { name, description?, owner_id? }
     */
    public function createTeam(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'owner_id' => ['sometimes', 'integer', 'exists:users,id'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $validator->errors()], 422);
        }

        $team = Team::create([
            'name' => $request->name,
            'description' => $request->input('description'),
            'personal_team' => false,
            'show_boarding' => false,
        ]);

        if ($request->filled('owner_id')) {
            $team->members()->attach($request->owner_id, ['role' => 'owner']);
        }

        return response()->json([
            'id' => $team->id,
            'name' => $team->name,
            'description' => $team->description,
        ], 201);
    }

    /**
     * Add a user to a team.
     *
     * POST /api/v1/subscription/teams/{team_id}/members
     *
     * Body: { user_id, role? }
     */
    public function addTeamMember(Request $request, int $teamId): JsonResponse
    {
        $team = Team::find($teamId);
        if (! $team) {
            return response()->json(['message' => 'Team not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['sometimes', 'string', 'in:owner,admin,member'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $validator->errors()], 422);
        }

        $role = $request->input('role', 'member');

        if ($team->members()->where('user_id', $request->user_id)->exists()) {
            $team->members()->updateExistingPivot($request->user_id, ['role' => $role]);
        } else {
            $team->members()->attach($request->user_id, ['role' => $role]);
        }

        return response()->json(['message' => 'Member added successfully.'], 200);
    }

    /**
     * Create a new plan.
     *
     * POST /api/v1/subscription/plans
     *
     * Body: { name, description?, price?, billing_cycle?, features?, is_active?, sort_order?,
     *         resources_limit?, projects_limit?, bandwidth_limit?, storage_limit?, team_members_limit? }
     */
    public function createPlan(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'billing_cycle' => ['sometimes', 'string', 'in:free,monthly,yearly'],
            'features' => ['sometimes', 'nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'resources_limit' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'projects_limit' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'bandwidth_limit' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'storage_limit' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'team_members_limit' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $validator->errors()], 422);
        }

        $plan = Plan::create([
            'name' => $request->name,
            'description' => $request->input('description'),
            'price' => $request->input('price', 0),
            'billing_cycle' => $request->input('billing_cycle', 'free'),
            'features' => $request->input('features', []),
            'is_active' => $request->input('is_active', true),
            'sort_order' => $request->input('sort_order', 0),
            'resources_limit' => $request->input('resources_limit'),
            'projects_limit' => $request->input('projects_limit'),
            'bandwidth_limit' => $request->input('bandwidth_limit'),
            'storage_limit' => $request->input('storage_limit'),
            'team_members_limit' => $request->input('team_members_limit'),
        ]);

        return response()->json([
            'id' => $plan->id,
            'name' => $plan->name,
            'billing_cycle' => $plan->billing_cycle,
            'price' => $plan->formattedPrice(),
        ], 201);
    }

    /**
     * Assign a plan to a team (creates or updates a TeamPlan subscription).
     *
     * POST /api/v1/subscription/assign
     *
     * Body: { team_id, plan_id, status?, starts_at?, expires_at?, notes? }
     */
    public function assignPlan(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'status' => ['sometimes', 'string', 'in:active,trial,cancelled,expired'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $validator->errors()], 422);
        }

        // Cancel any existing active/trial plan for this team
        TeamPlan::where('team_id', $request->team_id)
            ->whereIn('status', ['active', 'trial'])
            ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        $teamPlan = TeamPlan::create([
            'team_id' => $request->team_id,
            'plan_id' => $request->plan_id,
            'status' => $request->input('status', 'active'),
            'starts_at' => $request->input('starts_at', now()),
            'expires_at' => $request->input('expires_at'),
            'notes' => $request->input('notes'),
        ]);

        return response()->json([
            'id' => $teamPlan->id,
            'team_id' => $teamPlan->team_id,
            'plan_id' => $teamPlan->plan_id,
            'status' => $teamPlan->status,
            'starts_at' => $teamPlan->starts_at,
            'expires_at' => $teamPlan->expires_at,
        ], 201);
    }

    /**
     * Cancel a team's active subscription.
     *
     * POST /api/v1/subscription/cancel
     *
     * Body: { team_id }
     */
    public function cancelSubscription(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'team_id' => ['required', 'integer', 'exists:teams,id'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $validator->errors()], 422);
        }

        $updated = TeamPlan::where('team_id', $request->team_id)
            ->whereIn('status', ['active', 'trial'])
            ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        if ($updated === 0) {
            return response()->json(['message' => 'No active subscription found for this team.'], 404);
        }

        return response()->json(['message' => 'Subscription cancelled successfully.'], 200);
    }

    /**
     * Record a payment for a team.
     *
     * POST /api/v1/subscription/payments
     *
     * Body: { team_id, amount, currency?, status?, description?, paid_at?, team_plan_id? }
     */
    public function recordPayment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'status' => ['sometimes', 'string', 'in:paid,pending,failed,refunded'],
            'description' => ['sometimes', 'nullable', 'string'],
            'paid_at' => ['sometimes', 'nullable', 'date'],
            'team_plan_id' => ['sometimes', 'nullable', 'integer', 'exists:team_plans,id'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $validator->errors()], 422);
        }

        $payment = PaymentHistory::create([
            'team_id' => $request->team_id,
            'team_plan_id' => $request->input('team_plan_id'),
            'amount' => $request->amount,
            'currency' => strtoupper($request->input('currency', 'USD')),
            'status' => $request->input('status', 'paid'),
            'description' => $request->input('description'),
            'paid_at' => $request->input('paid_at', now()),
        ]);

        return response()->json([
            'id' => $payment->id,
            'team_id' => $payment->team_id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'status' => $payment->status,
            'paid_at' => $payment->paid_at,
        ], 201);
    }

    /**
     * Get all teams with their members and subscription data.
     *
     * GET /api/v1/subscription/teams
     */
    public function allTeams(): JsonResponse
    {
        $teams = Team::with(['members', 'currentTeamPlan.plan'])->get();

        $data = $teams->map(function (Team $team): array {
            $teamPlan = $team->currentTeamPlan;

            return [
                'id' => $team->id,
                'name' => $team->name,
                'description' => $team->description,
                'personal_team' => (bool) $team->personal_team,
                'created_at' => $team->created_at,
                'members' => $team->members->map(fn ($member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => $member->pivot->role,
                ]),
                'subscription' => $teamPlan ? [
                    'id' => $teamPlan->id,
                    'status' => $teamPlan->status,
                    'starts_at' => $teamPlan->starts_at,
                    'expires_at' => $teamPlan->expires_at,
                    'plan' => $teamPlan->plan ? [
                        'id' => $teamPlan->plan->id,
                        'name' => $teamPlan->plan->name,
                        'billing_cycle' => $teamPlan->plan->billing_cycle,
                        'price' => $teamPlan->plan->formattedPrice(),
                    ] : null,
                ] : null,
            ];
        });

        return response()->json($data);
    }

    /**
     * Get a team's current active subscription and usage.
     *
     * GET /api/v1/subscription/teams/{team_id}/status
     */
    public function teamSubscriptionStatus(int $teamId): JsonResponse
    {
        $team = Team::find($teamId);
        if (! $team) {
            return response()->json(['message' => 'Team not found.'], 404);
        }

        $teamPlan = $team->currentTeamPlan()->with('plan')->first();

        return response()->json([
            'team_id' => $team->id,
            'team_name' => $team->name,
            'subscription' => $teamPlan ? [
                'id' => $teamPlan->id,
                'status' => $teamPlan->status,
                'starts_at' => $teamPlan->starts_at,
                'expires_at' => $teamPlan->expires_at,
                'plan' => $teamPlan->plan ? [
                    'id' => $teamPlan->plan->id,
                    'name' => $teamPlan->plan->name,
                    'billing_cycle' => $teamPlan->plan->billing_cycle,
                    'price' => $teamPlan->plan->formattedPrice(),
                    'resources_limit' => $teamPlan->plan->resources_limit,
                    'projects_limit' => $teamPlan->plan->projects_limit,
                    'bandwidth_limit' => $teamPlan->plan->bandwidth_limit,
                    'storage_limit' => $teamPlan->plan->storage_limit,
                    'team_members_limit' => $teamPlan->plan->team_members_limit,
                ] : null,
            ] : null,
            'usage' => [
                'projects_count' => $team->projects()->count(),
                'resources_count' => $team->totalResourcesCount(),
                'team_members_count' => $team->members()->count(),
            ],
        ]);
    }
}
