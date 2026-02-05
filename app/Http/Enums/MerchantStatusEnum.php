<?php

namespace App\Http\Enums;

enum MerchantStatusEnum: int
{
    case UNPAID = 1;
    case PAID = 2;
    case BLOCKED = 3;
    case DELETED = 4;

    // Получение всех значений статусов
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    // Человеко-читаемые названия статусов
    public function label(): string
    {
        return match ($this) {
            self::UNPAID => 'Unpaid',
            self::PAID => 'Paid',
            self::BLOCKED => 'Blocked',
            self::DELETED => 'Deleted',
        };
    }
}
