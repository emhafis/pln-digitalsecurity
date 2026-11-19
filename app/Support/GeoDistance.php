<?php

namespace App\Support;

/**
 * Distance helpers used by backend geofence validation.
 */
final class GeoDistance
{
    private const EARTH_RADIUS_METERS = 6371000.0;

    /**
     * Haversine distance between two coordinates in meters.
     */
    public static function meters(
        float $latitude1,
        float $longitude1,
        float $latitude2,
        float $longitude2
    ): float {
        $latDelta = deg2rad($latitude2 - $latitude1);
        $lngDelta = deg2rad($longitude2 - $longitude1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($latitude1))
            * cos(deg2rad($latitude2))
            * sin($lngDelta / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_METERS * $c;
    }
}
