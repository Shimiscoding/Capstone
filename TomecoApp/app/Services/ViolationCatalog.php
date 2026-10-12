<?php

namespace App\Services;

use App\Models\ViolationIssued;

class ViolationCatalog
{
    public const COMMON_GROUPS = [
        'Driving without D/L', 'Unregistered Vehicle', 'Illegal parking',
        'Disregarding Traffic Sign', 'Obstruction', 'Truck Ban',
        'Operating Along National Highway', 'No helmet', 'Defective Head Light',
        'Anti-Littering', 'Anti-Smoking', 'Anti-Urinating and Defecating',
    ];

    public static function groupFor(string $label): string
    {
        $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $label)));

        return match ($normalized) {
            'no helmet' => 'No helmet',
            'illegal parking' => 'Illegal parking',
            'no driver\'s license', 'no drivers license', 'driving without d/l' => 'Driving without D/L',
            'unregistered vehicle' => 'Unregistered Vehicle',
            'disregarding traffic sign' => 'Disregarding Traffic Sign',
            'obstruction', 'road obstruction' => 'Obstruction',
            'truck ban' => 'Truck Ban',
            'operating along national highway' => 'Operating Along National Highway',
            'defective headlight', 'defective head light' => 'Defective Head Light',
            default => collect(self::COMMON_GROUPS)->first(fn ($group) => strtolower($group) === $normalized) ?? trim($label),
        };
    }

    /** Only split the old checkbox format when EVERY item is a known form option. */
    public static function legacyLabels(string $text): array
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', $text))));
        $known = collect($parts)->every(fn ($part) => in_array(self::groupFor($part), self::COMMON_GROUPS, true));

        return $known && count($parts) > 1 ? array_values(array_unique($parts)) : [trim($text)];
    }

    public static function formOptionFor(string $label): ViolationIssued
    {
        $group = self::groupFor($label);

        return ViolationIssued::where('label', in_array($group, self::COMMON_GROUPS, true) ? $group : 'Others')->firstOrFail();
    }

    public static function defaultGroupFor(object $type): string
    {
        if (isset($type->section_id)) {
            return $type->formOption->label;
        }
        if ($type->section_number === null) {
            return self::groupFor($type->description);
        }
        if ($type->ordinance_code !== 'CO 2000-01') {
            return 'Others';
        }

        return match ((string) $type->section_number) {
            '7' => 'Illegal parking', '16' => 'Obstruction', '19' => 'Unregistered Vehicle',
            '20' => 'Driving without D/L', '38', '42', '43', '44' => 'Disregarding Traffic Sign',
            default => 'Others',
        };
    }
}
