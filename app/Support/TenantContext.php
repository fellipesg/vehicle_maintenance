<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\User;

class TenantContext
{
    private static ?int $tenantId = null;

    public static function set(?int $tenantId): void
    {
        self::$tenantId = $tenantId;
    }

    public static function setFromUser(?User $user): void
    {
        self::$tenantId = $user?->tenant_id;
    }

    public static function id(): ?int
    {
        return self::$tenantId;
    }

    public static function clear(): void
    {
        self::$tenantId = null;
    }

    public static function tenant(): ?Tenant
    {
        if (! self::$tenantId) {
            return null;
        }

        return Tenant::find(self::$tenantId);
    }
}
