<?php

namespace App\Support;

use App\Models\Apar;
use App\Models\Hydrant;
use App\Models\Security;
use App\Models\User;
use Illuminate\Support\Carbon;

class AssetInspectionRules
{
    public const MAX_PHOTOS = 5;

    public const RESULT_OK = 'ok';

    public const RESULT_NOT_OK = 'not_ok';

    public const APAR_TYPES = ['dry_chemical', 'co2', 'foam', 'air', 'powder'];

    public const HYDRANT_TYPES = [
        'fire_pump_diesel',
        'fire_pump_electric',
        'jockey_pump',
        'hydrant_box',
        'hydrant_pillar',
        'siamese_connection',
    ];

    /**
     * @return array<int, array{item_name:string,item_label:string,sort_order:int}>
     */
    public static function aparChecklist(): array
    {
        return [
            ['item_name' => 'pressure_gauge', 'item_label' => 'Indikator Tekanan', 'sort_order' => 1],
            ['item_name' => 'operating_lever', 'item_label' => 'Tuas Pengoperasian', 'sort_order' => 2],
            ['item_name' => 'safety_pin_seal', 'item_label' => 'Pin Pengaman & Segel', 'sort_order' => 3],
            ['item_name' => 'nozzle', 'item_label' => 'Corong Semprot', 'sort_order' => 4],
            ['item_name' => 'hose', 'item_label' => 'Selang', 'sort_order' => 5],
            ['item_name' => 'discharge_orifice', 'item_label' => 'Lubang Keluar', 'sort_order' => 6],
            ['item_name' => 'carrying_handle', 'item_label' => 'Pegangan', 'sort_order' => 7],
            ['item_name' => 'body', 'item_label' => 'Badan Tabung', 'sort_order' => 8],
            ['item_name' => 'label', 'item_label' => 'Label & Penulisan', 'sort_order' => 9],
            ['item_name' => 'expiry_date', 'item_label' => 'Masa Berlaku', 'sort_order' => 10],
        ];
    }

    /**
     * @return array<int, array{item_name:string,item_label:string,sort_order:int}>
     */
    public static function hydrantChecklist(): array
    {
        return [
            ['item_name' => 'kondisi_baterai', 'item_label' => 'Kondisi Baterai', 'sort_order' => 1],
            ['item_name' => 'kondisi_pelumas', 'item_label' => 'Kondisi Pelumas', 'sort_order' => 2],
            ['item_name' => 'kondisi_air_radiator', 'item_label' => 'Kondisi Air Radiator', 'sort_order' => 3],
            ['item_name' => 'kondisi_bahan_bakar', 'item_label' => 'Kondisi Minyak / Bahan Bakar', 'sort_order' => 4],
            ['item_name' => 'pemanasan', 'item_label' => 'Pemanasan', 'sort_order' => 5],
            ['item_name' => 'kondisi_mesin', 'item_label' => 'Kondisi Mesin', 'sort_order' => 6],
            ['item_name' => 'pressure_gauge', 'item_label' => 'Pressure Gauge', 'sort_order' => 7],
            ['item_name' => 'kebocoran', 'item_label' => 'Kebocoran', 'sort_order' => 8],
            ['item_name' => 'panel_indikator', 'item_label' => 'Panel / Indikator', 'sort_order' => 9],
            ['item_name' => 'valve', 'item_label' => 'Valve', 'sort_order' => 10],
            ['item_name' => 'pipa_sambungan', 'item_label' => 'Pipa dan Sambungan', 'sort_order' => 11],
        ];
    }

    /**
     * @return array<int, array{item_name:string,item_label:string,sort_order:int}>
     */
    public static function checklist(string $assetType): array
    {
        return match (strtolower($assetType)) {
            'apar' => self::aparChecklist(),
            'hydrant' => self::hydrantChecklist(),
            default => throw new \InvalidArgumentException('Jenis aset inspeksi tidak valid.'),
        };
    }

    /**
     * @return array<int, string>
     */
    public static function checklistItemNames(string $assetType): array
    {
        return array_column(self::checklist($assetType), 'item_name');
    }

    /**
     * Compute inspection result: ok if all items good, not_ok if any item is not good.
     *
     * @param  array<int, array<string, mixed>|object>  $items
     */
    public static function resultForItems(array $items): string
    {
        foreach ($items as $item) {
            $isGood = is_array($item) ? ($item['is_good'] ?? null) : ($item->is_good ?? null);
            if ($isGood === false || $isGood === 0 || $isGood === '0') {
                return self::RESULT_NOT_OK;
            }
        }

        return self::RESULT_OK;
    }

    /**
     * Validation rules for inspection payload (reusable by APAR/Hydrant controllers).
     *
     * @return array<string, mixed>
     */
    public static function rules(string $assetType): array
    {
        $names = self::checklistItemNames($assetType);

        return [
            'items' => ['required', 'array', 'size:'.count($names)],
            'items.*.item_name' => ['required', 'string', 'in:'.implode(',', $names)],
            'items.*.is_good' => ['required', 'boolean'],
            'items.*.note' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'photos' => ['nullable', 'array', 'max:'.self::MAX_PHOTOS],
            'photos.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    /**
     * Ensure submitted checklist covers every required item exactly once.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, string> validation error messages (empty if valid)
     */
    public static function validateChecklistCompleteness(string $assetType, array $items): array
    {
        $required = self::checklistItemNames($assetType);
        $provided = [];
        foreach ($items as $item) {
            $name = $item['item_name'] ?? null;
            if (is_string($name) && $name !== '') {
                $provided[] = $name;
            }
        }

        sort($required);
        $sortedProvided = $provided;
        sort($sortedProvided);

        $errors = [];
        if (count($provided) !== count($required) || $sortedProvided !== $required || count($provided) !== count(array_unique($provided))) {
            $errors[] = 'Checklist inspeksi wajib diisi lengkap sesuai item yang ditentukan.';
        }

        return $errors;
    }

    /**
     * Validate notes requirement against result.
     *
     * @return array<int, string>
     */
    public static function validateNotesForResult(string $result, ?string $notes): array
    {
        if ($result === self::RESULT_NOT_OK && trim((string) $notes) === '') {
            return ['Catatan wajib diisi jika hasil inspeksi tidak baik.'];
        }

        return [];
    }

    public static function serverInspectedAt(): Carbon
    {
        return now();
    }

    public static function isSecurityUserEligible(?User $user): bool
    {
        if ($user === null || $user->role !== 'security' || ! $user->is_active) {
            return false;
        }

        if ($user->unit_id === null) {
            return false;
        }

        return Security::query()->where('user_id', $user->id)->exists();
    }

    /**
     * Asset must be active and belong to the security user's unit.
     *
     * @param  Apar|Hydrant  $asset
     */
    public static function canSecurityInspectAsset(User $user, $asset): bool
    {
        if (! self::isSecurityUserEligible($user)) {
            return false;
        }

        $unitId = $asset->getAttribute('unit_id');
        $isActive = (bool) $asset->getAttribute('is_active');

        return $isActive && $unitId !== null && (int) $unitId === (int) $user->unit_id;
    }

    /**
     * Build normalized checklist items for persistence (new record), in defined order.
     *
     * @param  array<int, array<string, mixed>>  $submitted keyed or listed items with item_name,is_good,note
     * @return array<int, array{item_name:string,item_label:string,is_good:bool,note:?string,sort_order:int}>
     */
    public static function normalizeItems(string $assetType, array $submitted): array
    {
        $byName = [];
        foreach ($submitted as $item) {
            $name = $item['item_name'] ?? null;
            if (is_string($name)) {
                $byName[$name] = $item;
            }
        }

        $normalized = [];
        foreach (self::checklist($assetType) as $def) {
            $raw = $byName[$def['item_name']] ?? [];
            $isGood = filter_var($raw['is_good'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $note = $raw['note'] ?? null;
            $note = is_string($note) ? trim($note) : null;
            $normalized[] = [
                'item_name' => $def['item_name'],
                'item_label' => $def['item_label'],
                'is_good' => $isGood,
                'note' => ($note === '') ? null : $note,
                'sort_order' => $def['sort_order'],
            ];
        }

        return $normalized;
    }
}
