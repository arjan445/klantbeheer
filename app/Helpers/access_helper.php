<?php

/**
 * Rechten (Shield). Admin mag altijd alles.
 *
 *   is_admin()
 *   user_can('vault.view')
 */

if (! function_exists('is_admin')) {
    function is_admin(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->inGroup('admin');
    }
}

if (! function_exists('user_can')) {
    function user_can(string $permission): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        return $user->inGroup('admin') || $user->can($permission);
    }
}
