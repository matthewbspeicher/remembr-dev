# Plan: Remove Billing and Payment Structure

Strip the subscription-based payment structure and grant full "Pro" access to all users by default.

## Objective
- Grant all users "Pro" access.
- Disable billing and pricing routes.
- Simplify `User` model by removing `Billable` and payment-related methods.
- Remove `EnforcePlanLimits` middleware or make it a no-op.
- Clean up UI to remove "Upgrade" prompts and billing management.

## Key Files & Context
- `app/Models/User.php`: Core user model with billing traits.
- `app/Http/Middleware/EnforcePlanLimits.php`: Plan restriction middleware.
- `routes/web.php`: Billing and pricing routes.
- `app/Http/Controllers/BillingController.php`: Billing-specific controller.
- `app/Http/Controllers/Auth/DashboardController.php`: Dashboard logic.
- `resources/js/Pages/Dashboard.vue`: Dashboard UI.

## Implementation Steps

### 1. Model Refactor (`app/Models/User.php`)
- Remove `Laravel\Cashier\Billable` trait.
- Simplify `isPro()` to always return `true`.
- Simplify `hasUnlimitedAgentAccess()` to return `true`.
- Update `maxAgents()` and `maxMemoriesPerAgent()` to return high limits (or PHP_INT_MAX).
- Simplify `canCreateWorkspace()` to return `true`.
- Remove `isDowngraded()`, `isOnGracePeriod()`, `hasPaymentFailure()`.
- Clean up `$hidden` and `$fillable` arrays.

### 2. Routes and Controllers
- Remove billing-related routes from `routes/web.php`.
- Delete `app/Http/Controllers/BillingController.php`.

### 3. Middleware Updates
- Make `EnforcePlanLimits` a no-op (return `$next($request)` immediately).
- Alternatively, remove it from all route groups and the `bootstrap/app.php` aliases.

### 4. Dashboard Logic (`DashboardController.php`)
- Remove billing-related variables passed to the frontend (e.g., `isPro`, `currentPlan`, `isDowngraded`).

### 5. UI Cleanup
- Remove "Pricing" link from navigation.
- Remove "Upgrade to Pro" banners/prompts in the Dashboard.
- Remove "Manage Subscription" buttons.

### 6. Verification & Testing
- Run existing tests to identify any broken logic.
- Verify that agent/workspace creation is now unrestricted for all users.
- Verify that billing routes return 404 or are redirected.

## Verification
- `php artisan test` (some tests might need to be removed or updated).
- Manual check of the Dashboard to ensure it looks "Pro" for everyone.
