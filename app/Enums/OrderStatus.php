<?php

namespace App\Enums;

enum OrderStatus: string
{
    case RECEIVED = 'received';
    case PREPARING = 'preparing';
    case READY = 'ready';
    case COMPLETED = 'completed';

    private const ACTIVE_CASES = [self::RECEIVED, self::PREPARING, self::READY];

    public function isActive(): bool
    {
        return in_array($this, self::ACTIVE_CASES);
    }

    /**
     * Get the active status values for database queries
     */
    public static function activeValues(): array
    {
        return array_map(fn($case) => $case->value, self::ACTIVE_CASES);
    }
}
