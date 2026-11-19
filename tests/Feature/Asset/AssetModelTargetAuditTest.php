<?php

namespace Tests\Feature\Asset;

use App\Models\Apar;
use App\Models\Hydrant;
use Tests\TestCase;

class AssetModelTargetAuditTest extends TestCase
{
    public function test_apar_model_matches_target_fillable_and_casts(): void
    {
        $this->assertSame([
            'unit_id',
            'apar_code',
            'apar_type',
            'brand',
            'capacity',
            'serial_number',
            'procurement_date',
            'installation_date',
            'recharge_date',
            'expiry_date',
            'placement',
            'qr_code',
            'photo',
            'is_active',
        ], (new Apar)->getFillable());

        $casts = (new Apar)->getCasts();
        $this->assertSame('date', $casts['procurement_date']);
        $this->assertSame('date', $casts['installation_date']);
        $this->assertSame('date', $casts['recharge_date']);
        $this->assertSame('date', $casts['expiry_date']);
        $this->assertSame('boolean', $casts['is_active']);
    }

    public function test_hydrant_casts_match_target_until_column_rename_is_approved(): void
    {
        $casts = (new Hydrant)->getCasts();
        $this->assertSame('date', $casts['procurement_date']);
        $this->assertSame('date', $casts['installation_date']);
        $this->assertSame('date', $casts['last_maintenance_date']);
        $this->assertSame('boolean', $casts['is_active']);

        $fillable = (new Hydrant)->getFillable();
        $this->assertContains('hydrant_code', $fillable);
        $this->assertContains('hydrant_type', $fillable);
    }
}
