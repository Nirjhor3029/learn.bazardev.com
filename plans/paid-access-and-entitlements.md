# Plan: Paid courses, admin-controlled free access, and a paid terminal add-on

**Status:** Phase 1 and Phase 4 (§7) are **live** on learn.bazardev.com as of 2026-11-20. Phase 1: the data model, `Entitlements` service, the `EnsureCourseEntitlement` paywall middleware, and the two admin screens (`/admin/pricing`, `/admin/entitlements`) — tested (126/126 `php artisan test` at that point). Phase 4: `terminal_launch_tickets.course_slug` added, `TerminalLaunchController::launch()` now checks `canAccessTerminal()` before minting a ticket — tested (131/131 overall, +5 in `tests/Feature/TerminalLaunchTest.php`). **No course is actually paid yet** — every course still has no `course_pricing` row, so every chapter remains exactly as open as before this shipped (see the safe-by-default note below). **The terminal, by contrast, is now gated for everyone** — nobody has a `terminal` entitlement yet, so any reader who was actively using it needs an admin grant via `/admin/entitlements` to keep using it (this was the deliberate, specified behavior — the terminal has no "stay open until configured" safe default the way courses do, because it was already explicitly meant to require its own purchase). Phases 2-3 (real pay.bazardev payment, subscriptions) are **not started**; §8's open items still block Phase 2.

One implementation-time deviation from the original plan, worth reading before touching this again: **§3.1's "no row = not free, no price set = blocked" default was flipped to the opposite** once actually building it — a course with no `course_pricing` row is now **open/free**, not locked. The original "blocked by default" rule would have instantly locked every chapter (except each course's free-preview Chapter 1) across the entire site — including all 9 free core-path courses — the moment the migration ran, with zero admin action taken. The admin now switches the paywall on **per course, explicitly**, by unchecking "Free" on `/admin/pricing` — nothing is ever silently locked by a migration running. `App\Models\CoursePricing::isFree()` and its docblock reflect this; treat that method's current behavior as the real spec, not this document's original §3.1 wording.

## 1. The owner's requirements, as given

- Every course on learn.bazardev.com moves behind payment: a user pays, then can read/study it.
- Admin can mark any specific course **free** (no payment needed).
- Admin can set/change the **price** of any course at any time.
- Admin can **manually grant a specific user free access** to a course, bypassing payment entirely (an approval/override action).
- **terminal.bazardev.com must also become paid** — and specifically as a **separate add-on**: buying a course does *not* automatically unlock its terminal; the terminal is its own purchase even for a course the user already owns.
- Payment collection: **bKash and Nagad numbers**, connected through **pay.bazardev** (the owner's own payment gateway/API) — not SSLCommerz, not Stripe, not a direct bKash Merchant API integration.
- Pricing model: **both** one-time per-course purchase **and** a subscription option should exist.
- Existing users who already have progress (chapter-done marks, bookmarks, notes) on a course: **no automatic grandfathering** — the admin will manually decide, case by case, who gets free access when payment goes live.

## 2. What exists today (confirmed by reading the code, not assumed)

Nothing payment-related exists anywhere in either codebase right now. Specifically, confirmed by a full grep across `app/`, `database/migrations/`, `routes/`, `config/` for payment/price/purchase/subscription/enroll/stripe/sslcommerz/bkash/nagad: **zero matches**.

- **Course reading is currently fully public.** `App\Services\Learning::catalog()` (`app/Services/Learning.php`) builds the readable course/chapter list purely from `config('course.courses')`, filtering on `status === 'available'`. No user or payment check exists in it. `CourseController::chapter()` has an explicit comment: *"Reading is public. A logged-in reader also gets their own state."*
- **No course-access middleware exists.** `app/Http/Middleware/` only has `EnsureAdmin` (checks `users.is_admin`, a single boolean column — no roles/permissions table) and `EnsureTerminalSharedSecret` (server-to-server secret for the terminal verify endpoint).
- **`config/course.php` is static PHP**, not database rows — each course is a keyed array (`title`, `tagline`, `folder`, `requires`, `status`, `section`, `chapters`). There is nowhere in it an admin could edit a price at runtime without a code deploy.
- **The admin panel today** (`app/Http/Controllers/Admin/`) only has user management (list/search/verify email) and community-contribution approval. No pricing or access-grant screens exist.
- **terminal.bazardev.com has no payment or course concept at all.** `PROJECT_CONTEXT.md` states directly: *"No course-progress gating in v1 — any logged-in reader sees it [the Open Terminal button]."* The ticket flow (`TerminalLaunchController::launch()` → `terminal_launch_tickets` table → orchestrator `GET /launch`, `orchestrator/server.js` lines 157-204) carries only `{id, name, email}` — no course field, no entitlement check anywhere. The system is also **architecturally single-course today**: the Docker image (`course1-terminal:v1`), network (`course1-net`), and container names (`course1-session-${user.id}`) are all hardcoded to the one existing course (Linux & Shell Basics), not parameterized per course.

This last point matters for scope: "make the terminal paid" today effectively means *"gate the one existing terminal product behind its own purchase"* — not *"build a general per-course terminal marketplace."* Generalizing the terminal to multiple courses is a separate, bigger piece of work than payment itself; this plan treats it as a later phase, not a prerequisite (see §7, Phase 5).

## 3. Data model (all net-new)

### 3.1 `course_pricing` table (one row per course slug)

Keeps commercial metadata editable by admin at runtime, while `config/course.php` stays the source of truth for course *content* (title, chapters, folder) exactly as it is today — this table only adds the money/access layer on top.

| Column | Type | Notes |
|---|---|---|
| `course_slug` | string, primary key | matches the key in `config('course.courses')` |
| `is_free` | boolean, default false | admin toggle — when true, every user gets access regardless of payment |
| `price_amount` | integer | stored in whole BDT (poisha-level precision not needed for this use case — confirm with owner before building) |
| `currency` | string, default `BDT` | future-proofing only; not expected to change |
| `subscription_eligible` | boolean, default true | whether this course is included when a user buys the all-access subscription |
| `updated_by` | foreign key → `users.id`, nullable | which admin last changed price/free status |
| timestamps | | |

A course with no row in this table yet should be treated as **not free, no price set** — i.e. purchasable only once an admin explicitly sets a price, so nothing accidentally becomes payable (or free) by omission. This needs an explicit "launch checklist" step per course before the paywall goes live for it (§8).

### 3.2 `products` concept (course purchase, terminal add-on, subscription — one shape)

Rather than three different tables for three different things a user can buy, one `entitlements` table with a `product_type` + `product_key` pair covers all of them cleanly:

| Column | Type | Notes |
|---|---|---|
| `id` | | |
| `user_id` | foreign key → `users.id` | |
| `product_type` | enum: `course`, `terminal`, `subscription_all_access` | |
| `product_key` | string, nullable | course slug for `course`/`terminal`; null for `subscription_all_access` |
| `source` | enum: `purchase`, `admin_grant`, `free_course` | how this entitlement came to exist — `free_course` is a cached/derived row written when a course is marked free, so access checks only ever need to query one table |
| `granted_by` | foreign key → `users.id`, nullable | which admin, if `source = admin_grant` |
| `grant_note` | text, nullable | why — admin-entered, for audit/accountability |
| `payment_transaction_id` | foreign key → `payment_transactions.id`, nullable | null for admin grants / free courses |
| `starts_at` | timestamp | |
| `expires_at` | timestamp, nullable | null = permanent (one-time purchase, admin grant); set = subscription period end |
| `revoked_at` | timestamp, nullable | for refunds/chargebacks/admin revocation — soft, never hard-delete an entitlement row (audit trail) |
| timestamps | | |

### 3.3 `payment_transactions` table (the billing audit trail, separate from access control)

Access control should never read this table directly — it only ever reads `entitlements`. This table exists purely so every krona/taka that moved through pay.bazardev is traceable, independent of whether the resulting entitlement was later revoked.

| Column | Type | Notes |
|---|---|---|
| `id` | | |
| `user_id` | foreign key | |
| `intent` | enum: `course_purchase`, `terminal_purchase`, `subscription` | what was being bought |
| `product_key` | string, nullable | course slug, or null for subscription |
| `amount` | integer | |
| `currency` | string | |
| `payment_method` | string | `bkash` / `nagad` — as reported back by pay.bazardev |
| `gateway_reference` | string, unique, indexed | pay.bazardev's own transaction id — the idempotency key (§4) |
| `status` | enum: `pending`, `completed`, `failed`, `refunded` | |
| `raw_payload` | json | the gateway's own response/webhook body, kept for dispute resolution — **never log a raw card/wallet number here if pay.bazardev's response ever includes one; mask before storing** |
| timestamps | | |

### 3.4 `terminal_launch_tickets` — one new column

Add `course_slug` (string) to the existing table (`database/migrations/2026_11_18_085316_create_terminal_launch_tickets_table.php`'s successor). Today the ticket has no notion of which course it's for at all, because there is only one. Adding this now costs nothing and means the entitlement check (§5.3) has something concrete to check against, and leaves room for §7 Phase 5 without another migration later.

## 4. Payment integration: pay.bazardev

A thin interface, not a hard dependency on pay.bazardev's exact API shape, so a second gateway could be added later without touching call sites:

```
interface PaymentGateway {
    initiate(PaymentIntent $intent): PaymentSession;   // returns a redirect URL / checkout token
    verifyWebhook(Request $request): PaymentResult;    // validates signature, returns normalized result
}
```

`App\Services\Payments\PayBazarDevGateway implements PaymentGateway` is the only concrete implementation needed now. Exact request/response shapes, auth (API key? HMAC signature?), and bKash-vs-Nagad selection UX on pay.bazardev's own checkout page are **unknowns until pay.bazardev's API docs/credentials are available** — this plan can't finalize that class's internals yet; it's flagged in §8 as a blocking open item, not guessed at.

**Flow:**
1. User clicks "Buy" on a course/terminal/subscription page → app creates a `payment_transactions` row (`status = pending`) → calls `PaymentGateway::initiate()` → redirects the user to pay.bazardev's hosted checkout.
2. User pays via bKash or Nagad on pay.bazardev's own page — this app never touches the wallet number or OTP.
3. **Two return paths, and only one of them is trusted:**
   - A browser redirect back to a `success`/`fail` URL — used only to show the user a "thanks" or "failed" page. **Never used to grant access on its own**, since a redirect can be abandoned, retried, or (in principle) forged by a client.
   - A server-to-server **webhook** (`POST /webhooks/pay-bazardev`) — this is the only thing allowed to mark a `payment_transactions` row `completed` and create the resulting `entitlements` row. Must verify pay.bazardev's signature on every call, and must be **idempotent** keyed on `gateway_reference` (payment gateways routinely retry/duplicate webhook delivery — processing the same reference twice must be a no-op, not a double-grant or a crash).

## 5. Access control changes

### 5.1 `EntitlementService` (new)

One place every access decision goes through:

- `canAccessCourse(User $user, string $courseSlug): bool` — true if: the course's `course_pricing.is_free` is true, **or** the user has a non-revoked, non-expired `subscription_all_access` entitlement, **or** the user has a non-revoked, non-expired `course` entitlement for that slug (purchase or admin grant).
- `canAccessTerminal(User $user, string $courseSlug): bool` — checks for a `terminal` entitlement for that slug specifically. **Deliberately does not fall back to course ownership** — this is the "separate add-on" requirement from §1; owning the course grants nothing here on its own.
- `hasActiveSubscription(User $user): bool` — helper used by both of the above and by account/billing pages.

### 5.2 Course reading (learn.bazardev.com)

- The **course overview/syllabus page** (`GET /{course}`) stays public — people need to see what a course covers and its price before buying it.
- **Chapter pages** (`GET /{course}/{chapter}`) get a new `EnsureCourseEntitlement` middleware, applied after the existing catalog-existence check, before content is returned. Not entitled → redirect to that course's pricing/buy page, not a bare 403.
- **Open decision, flagged for the owner, not decided here:** should **Chapter 1** of every course stay a free preview (common pattern — lets someone evaluate quality before paying) even once the course is otherwise paid? This changes `EnsureCourseEntitlement`'s logic by exactly one line (`chapter === first chapter → allow`) but is a product decision, not a technical one. Needs an answer before this middleware ships.
- Quizzes/exams/certificates: a user who already earned a certificate keeps it permanently (it's proof of a past achievement, not ongoing access) even if a subscription later lapses or an admin-granted entitlement is revoked. Re-reading the chapter content or re-attempting the quiz, however, follows the normal entitlement check like any other chapter page.

### 5.3 Terminal launch (both repos)

- `TerminalLaunchController::launch()` gains a required `course` route/query parameter, calls `EntitlementService::canAccessTerminal()` **before** minting a ticket. Not entitled → a "buy terminal access" page, no ticket issued at all.
- The ticket itself carries `course_slug` (§3.4). Because the entitlement check already happened at mint time, and tickets are one-time-use and expire in 60 seconds (existing design), **the orchestrator's own verify flow (`server.js` lines 157-204) needs no new logic** — it keeps trusting a valid, unused, unexpired ticket exactly as it does today. This keeps the payment check in exactly one place (learn.bazardev.com) and leaves the orchestrator's trust boundary unchanged, which matches how carefully that project's existing ticket design was built.
- `TerminalLaunchTicket::verify()`'s response can optionally echo back `course_slug` for the orchestrator's logging, but no behavior depends on it.

### 5.4 Admin UI (new)

- **`Admin/CoursePricingController`** — one page listing every course from `config('course.courses')` next to its `course_pricing` row (or "not configured yet" if none exists): toggle free/paid, set price, toggle subscription eligibility. Every change writes `updated_by` = the acting admin.
- **`Admin/EntitlementController`** — search a user (reuses the existing `Admin/UserController` search), pick a course (or "terminal: <course>" or "all-access subscription"), grant or revoke, with a required note field (why) for the audit trail. Lists a user's current entitlements and full history (including revoked ones) on the same screen.
- **Nice-to-have, not required for launch:** a report view — "users with chapter-done/bookmark activity on course X but no entitlement" — sorted by engagement, so when the admin manually decides who gets free access (§1's explicit answer for existing users), they're working from a ranked list instead of guessing. Propose building this alongside the grant UI since it directly serves the exact workflow the owner described.

### 5.5 User-facing (new)

- A pricing/buy section on each course's overview page: price, a "Buy" button if unowned, an "Owned" / "Subscribed" badge if already entitled.
- A separate, visually distinct "Terminal access" buy button wherever the terminal launch link currently appears, since it's a different purchase from the course itself.
- An account page listing the user's own purchases/entitlements and subscription status/renewal date.

## 6. Security and correctness notes to carry into implementation

- **Webhook signature verification is mandatory, not optional** — this is the single most security-critical line in the whole feature; a forged webhook call is a free course/subscription.
- **Idempotency on webhook processing**, keyed on `gateway_reference` — duplicate delivery is normal gateway behavior, not an edge case.
- **Refunds/chargebacks:** revoking an entitlement should be a soft `revoked_at` write, never a delete — both for audit and because a certificate already earned must survive it (§5.2).
- **Subscription expiry:** needs an explicit decision on grace period (does access end exactly at `expires_at`, or is there a short grace window before lockout?) — not decided here, flagged for the owner.
- **Nothing becomes payable by accident:** a course with no `course_pricing` row is access-**blocked**-by-default once the paywall middleware ships, not silently free and not silently paid — each course needs an explicit admin decision before its paywall goes live (ties into the phased rollout, §7).
- **No secrets in the repo:** pay.bazardev API keys/webhook signing secrets go in `.env`, never committed — standard for this codebase already, just restated here since this feature is the first to need them.

## 7. Phased rollout

Doing all of this in one shot is both unnecessary and riskier than it needs to be — this can ship in independently useful slices:

1. **Phase 1 — data model + admin control, no real payment yet.** Migrations for `course_pricing`, `entitlements`, `payment_transactions`; `EntitlementService`; the admin pricing and grant-entitlement screens; the `EnsureCourseEntitlement` middleware. At the end of this phase, the admin can already mark courses free/paid and manually grant access — which is exactly the tool needed to execute the "admin manually decides who gets free access" decision for existing users (§1) *before* real payment collection even exists.
2. **Phase 2 — pay.bazardev integration.** `PayBazarDevGateway`, the checkout redirect flow, the webhook endpoint, the buy buttons on course pages. Blocked on pay.bazardev API docs/credentials (§8).
3. **Phase 3 — subscription product.** The `subscription_all_access` entitlement type, renewal/recurring-charge handling via pay.bazardev, the expiry/grace-period logic from §6.
4. **Phase 4 — terminal add-on.** The `course_slug` column on tickets, the `canAccessTerminal` gate in `TerminalLaunchController::launch()`, the separate terminal buy button.
5. **Phase 5 — optional, not committed, flagged for later** — generalizing terminal.bazardev.com beyond its current single hardcoded course (image, network, container naming all currently fixed to Linux & Shell Basics), if the owner ever wants a terminal attached to a second course. Out of scope for the payment feature itself.

## 8. Open items — need the owner's answer before the relevant phase starts

- **pay.bazardev API details** (base URL, auth method, webhook signing scheme, sandbox/test credentials) — blocks all of Phase 2.
- **Actual prices** per course, and the subscription price — pure business decision, not needed until Phase 1's admin UI is ready to use.
- **Whether Chapter 1 of every course stays a free preview** once paid (§5.2) — needed before `EnsureCourseEntitlement` ships.
- **Subscription grace period on expiry** (§6) — needed before Phase 3.
- **Per-course launch order**: does the paywall go live for all courses at once, or one at a time (giving the admin time to run the manual free-access review per course before each one locks)? Given the "no automatic grandfathering, admin decides manually" answer, going course-by-course seems safer — flagging as the recommended default, but it's the owner's call.
