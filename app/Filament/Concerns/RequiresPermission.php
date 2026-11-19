<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Resource-level authorisation by permission name. Super admins pass via Gate::before.
 * Set static $permission (view + manage) or $viewPermission for read access.
 */
trait RequiresPermission
{
    protected static function allows(?string $permission): bool
    {
        $user = auth()->user();

        return $user && $user->is_active && $user->isStaff() && $permission && $user->can($permission);
    }

    public static function canViewAny(): bool
    {
        return static::allows(static::$viewPermission ?? static::$permission);
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return static::allows(static::$permission);
    }

    public static function canEdit(Model $record): bool
    {
        return static::allows(static::$permission);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
