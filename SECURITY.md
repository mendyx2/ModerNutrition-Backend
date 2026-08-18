# ModerNutrition Security & Compliance Architecture

This document details the security model, guarantees, and compliance controls implemented across the ModerNutrition API platform.

---

## 1. Authentication & Session Security

- **Token Protocol**: Laravel Sanctum bearer tokens with a 60-minute default expiration.
- **Single Token Guard with Additive Roles**: Both member portal (`ModerNutrition-Dev`) and administrator portal (`ModerNutrition-Admin`) authenticate against a unified Sanctum guard.
  - A single member token holds additive roles (`Consumer`, `Distributor`, `Leader`, `Country Operations`, `Global Administration`).
  - Permissions are resolved server-side via Spatie Laravel Permission middleware on every endpoint.
- **In-Memory Token Lifecycle**: Client frontends retain bearer tokens in memory/React state only. Tokens are never written to `localStorage` or `sessionStorage` in authenticated contexts.
- **Rate-Limiting & Lockout**:
  - `POST /api/auth/login` enforces a rate-limit threshold of **5 failed attempts per 5-minute window** keyed by `email + IP`.
  - Upon exceeding the limit, the route returns HTTP 422/429 with the exact seconds until unlock.
  - Successful authentication clears the rate limiter cache immediately.

---

## 2. Maker-Checker Withdrawal Controls

The platform enforces a strict dual-control (Maker-Checker) workflow on all financial withdrawals to eliminate internal embezzlement or unauthorized balance extractions:

1. **Maker Phase (`MemberWalletController@requestWithdrawal`)**:
   - The requester (member or country operator) submits a withdrawal request.
   - The requested amount is held in `wallets.pending_withdrawal`, decrementing `wallets.available_balance`.
   - The record stores `requester_id = auth_member_id` with `status = 'pending'`.
2. **Checker Phase (`AdminWithdrawalController@approve`)**:
   - The reviewing administrator must approve the request.
   - **Server-Side Enforcement**:
     ```php
     if ($withdrawal->requester_id === $approver->id) {
         AuditLog::record('withdrawal.maker_checker_rejected', ...);
         return response()->json([
             'message' => 'Maker-Checker Policy: You cannot approve a withdrawal request that you initiated.',
             'error_code' => 'MAKER_CHECKER_VIOLATION'
         ], 403);
     }
     ```
   - Same-user approvals are rejected with **HTTP 403 Forbidden** and immediately trigger an audit log security violation entry.
   - Approved withdrawals debit `wallets.pending_withdrawal`, increment `wallets.total_withdrawn`, and record an append-only row in `ledger_entries`.

---

## 3. Append-Only Ledger & Reversal Guarantees

The Commission Value (CV) Commerce Pool ledger is designed with strict accounting immutability:

1. **No Update Path**: The `ledger_entries` table deliberately has **no `updated_at` column** and no update methods exposed in the application layer.
2. **Immutable Plan Version**: Every ledger entry permanently captures the `plan_version_id` active at creation time. Activating a newer compensation plan version never retroactively alters historical calculations.
3. **Reversal Audit Trail**:
   - Adjustments or order refunds generate a **new reversal row** with `is_reversal = true`, `reversal_reference = original_entry_id`, and a negated amount.
   - The original entry remains permanently intact in the ledger history.
4. **Reconciled Plan Activation**:
   - Compensation plans cannot transition from `Draft → Approved → Active` unless the sum of active category percentages equals exactly **100.0000%** of the CV basis (`PlanVersionActivationService`).

---

## 4. Comprehensive Audit Logging (`audit_log`)

All configuration changes, administrative overrides, status transitions, and authentication events automatically record an immutable row in the `audit_log` table:

- **Actor Attribution**: Stores `actor_id`, `actor_email`, `actor_ip`, and `actor_user_agent`.
- **Surviving Deletion**: `actor_email` is denormalized so the audit trail survives even if an administrator account is deleted.
- **Before / After State**: Serialized JSON snapshots of `old_values` and `new_values`.
- **Monitored Actions**:
  - Member approval, suspension, and manual rank overrides.
  - Product price, PV, and CV changes.
  - Order status transitions (triggering CV allocation).
  - Plan version creation, approval, and activation.
  - Withdrawal approvals, rejections, and security violations.
  - Marketing asset uploads and deletions.

---

## 5. Data Privacy & Secret Hygiene

- **Sensitive Attributes**: `password`, `remember_token`, and `national_id` are permanently declared in `$hidden` on the `Member` model and are never exposed in JSON responses.
- **Encrypted Banking Details**: Withdrawal payout details (`payment_details`) are stored using Laravel's native `'encrypted:array'` Eloquent cast.
- **Zero Committed Secrets**: `.env` and sensitive credentials are fully excluded in `.gitignore`. Only `.env.example` with placeholder strings is checked into the repository.
