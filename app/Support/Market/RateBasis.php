<?php

namespace App\Support\Market;

/** What a price in a Need or Offer is per: the whole thing, a time span, or a unit of work or goods. */
enum RateBasis: string
{
    case Total = 'total';
    case Hour = 'hour';
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';
    case Item = 'item';
    case Kilogram = 'kilogram';
    case SquareMetre = 'square_metre';
    case Session = 'session';
    case Trip = 'trip';
    case Task = 'task';
    case Milestone = 'milestone';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $basis): string => $basis->value, self::cases());
    }
}
