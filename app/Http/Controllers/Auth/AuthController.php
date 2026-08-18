<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Member;
use App\Models\Rank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/auth/register
     * Register a new Consumer or Distributor.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name'         => 'required|string|max:100',
            'last_name'          => 'required|string|max:100',
            'email'              => 'required|email|unique:members,email',
            'password'           => 'required|string|min:8|confirmed',
            'phone'              => 'nullable|string|max:30',
            'country'            => 'required|string|size:3', // ISO alpha-3 e.g. COD
            'currency'           => 'required|string|size:3', // ISO 4217 e.g. USD, CDF
            'sponsor_code'       => 'nullable|string|exists:members,member_number',
            'placement_leg'      => 'nullable|in:left,right',
        ]);

        // Resolve sponsor if referral code provided
        $sponsor = null;
        if (!empty($validated['sponsor_code'])) {
            $sponsor = Member::where('member_number', $validated['sponsor_code'])->first();
        }

        // Default to Consumer rank (level 1)
        $defaultRank = Rank::where('level', 1)->first();

        $member = Member::create([
            'member_number'      => Member::generateMemberNumber(),
            'first_name'         => $validated['first_name'],
            'last_name'          => $validated['last_name'],
            'email'              => $validated['email'],
            'password'           => Hash::make($validated['password']),
            'phone'              => $validated['phone'] ?? null,
            'country'            => strtoupper($validated['country']),
            'currency'           => strtoupper($validated['currency']),
            'sponsor_id'         => $sponsor?->id,
            'parent_id'          => $sponsor?->id, // Default binary parent to sponsor if not specified
            'leg'                => $validated['placement_leg'] ?? 'left',
            'current_rank_id'    => $defaultRank?->id,
            'status'             => 'active', // Active immediately for consumer purchases
        ]);

        // Assign default Consumer role
        $member->assignRole('Consumer');

        // Create Sanctum API token
        $token = $member->createToken('auth-token')->plainTextToken;

        AuditLog::record(
            event: 'auth.registered',
            actor: $member,
            subject: $member,
            oldValues: [],
            newValues: ['member_number' => $member->member_number, 'email' => $member->email],
            metadata: ['sponsor_id' => $sponsor?->id],
            description: "New member #{$member->member_number} registered"
        );

        return response()->json([
            'message'     => 'Registration successful.',
            'token'       => $token,
            'member'      => $this->formatMemberResponse($member),
            'roles'       => $member->getRoleNames(),
            'permissions' => $member->getAllPermissions()->pluck('name'),
        ], 201);
    }

    /**
     * POST /api/auth/login
     * Rate-limited login with progressive lockout for both member and admin portals.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $throttleKey = 'login:' . strtolower($validated['email']) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => ["Too many login attempts. Please try again in {$seconds} seconds."],
            ]);
        }

        $member = Member::where('email', $validated['email'])->first();

        if (!$member || !Hash::check($validated['password'], $member->password)) {
            RateLimiter::hit($throttleKey, 300); // 5 min decay
            throw ValidationException::withMessages([
                'email' => ['Invalid email or password credentials provided.'],
            ]);
        }

        if ($member->status === 'suspended' || $member->status === 'terminated') {
            return response()->json([
                'message' => "Your account is currently {$member->status}. Please contact platform operations.",
            ], 403);
        }

        // Reset rate limiter on successful authentication
        RateLimiter::clear($throttleKey);

        // Delete old tokens on fresh login
        $member->tokens()->delete();
        $token = $member->createToken('auth-token')->plainTextToken;

        AuditLog::record(
            event: 'auth.login',
            actor: $member,
            subject: $member,
            description: "Member {$member->email} logged in"
        );

        return response()->json([
            'message'     => 'Login successful.',
            'token'       => $token,
            'member'      => $this->formatMemberResponse($member),
            'roles'       => $member->getRoleNames(),
            'permissions' => $member->getAllPermissions()->pluck('name'),
        ]);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $member = $request->user();
        if ($member) {
            $member->currentAccessToken()?->delete();
            AuditLog::record('auth.logout', $member, $member, description: "Member {$member->email} logged out");
        }

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * POST /api/auth/refresh
     */
    public function refresh(Request $request): JsonResponse
    {
        $member = $request->user();
        $member->currentAccessToken()?->delete();
        $newToken = $member->createToken('auth-token')->plainTextToken;

        return response()->json([
            'token'       => $newToken,
            'roles'       => $member->getRoleNames(),
            'permissions' => $member->getAllPermissions()->pluck('name'),
        ]);
    }

    /**
     * GET /api/auth/me
     * Returns current user identity, roles, and permissions for progressive frontend disclosure.
     */
    public function me(Request $request): JsonResponse
    {
        $member = $request->user();
        $member->load(['currentRank', 'sponsor:id,member_number,first_name,last_name']);

        return response()->json([
            'member'      => $this->formatMemberResponse($member),
            'roles'       => $member->getRoleNames(),
            'permissions' => $member->getAllPermissions()->pluck('name'),
        ]);
    }

    protected function formatMemberResponse(Member $member): array
    {
        return [
            'id'            => $member->id,
            'member_number' => $member->member_number,
            'first_name'    => $member->first_name,
            'last_name'     => $member->last_name,
            'full_name'     => $member->full_name,
            'email'         => $member->email,
            'phone'         => $member->phone,
            'country'       => $member->country,
            'currency'      => $member->currency,
            'status'        => $member->status,
            'leg'           => $member->leg,
            'sponsor'       => $member->sponsor ? [
                'member_number' => $member->sponsor->member_number,
                'name'          => $member->sponsor->full_name,
            ] : null,
            'current_rank'  => $member->currentRank ? [
                'id'    => $member->currentRank->id,
                'name'  => $member->currentRank->name,
                'level' => $member->currentRank->level,
            ] : null,
        ];
    }
}
