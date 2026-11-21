<?php

namespace App\Console\Commands;

use App\Models\AparInspectionPhoto;
use App\Models\GuestVisit;
use App\Models\HydrantInspectionPhoto;
use App\Models\PatrolScan;
use App\Models\PatrolScanPhoto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MigrateDsmsPhotosToPublic extends Command
{
    protected $signature = 'dsms:migrate-photos-to-public {--dry-run : Show what would be migrated without copying files}';

    protected $description = 'Idempotently copy DSMS operational photos from storage/app/private to storage/app/public.';

    public function handle(): int
    {
        $paths = collect()
            ->merge(PatrolScanPhoto::query()->pluck('photo_path'))
            ->merge(PatrolScan::query()->whereNotNull('incident_photo_path')->pluck('incident_photo_path'))
            ->merge(GuestVisit::query()->pluck('selfie_path'))
            ->merge(GuestVisit::query()->pluck('signature_path'))
            ->merge(AparInspectionPhoto::query()->pluck('photo_path'))
            ->merge(HydrantInspectionPhoto::query()->pluck('photo_path'))
            ->filter(fn ($path) => is_string($path) && trim($path) !== '')
            ->map(fn (string $path) => str_replace('\\', '/', trim($path)))
            ->unique()
            ->values();

        $dryRun = (bool) $this->option('dry-run');
        $copied = 0;
        $already = 0;
        $missingSource = 0;
        $failed = 0;
        $mismatched = 0;

        foreach ($paths as $path) {
            if (str_contains($path, '..') || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path)) {
                $this->warn("Skipped unsafe path: {$path}");
                $failed++;
                continue;
            }

            $source = storage_path('app/private/'.$path);
            $target = storage_path('app/public/'.$path);

            if (! File::exists($source)) {
                if (File::exists($target)) {
                    $already++;
                } else {
                    $missingSource++;
                    $this->warn("Source missing: {$path}");
                }
                continue;
            }

            if (File::exists($target)) {
                if (hash_file('sha256', $source) === hash_file('sha256', $target)) {
                    $already++;
                } else {
                    $mismatched++;
                    $this->warn("Target exists but differs: {$path}");
                }
                continue;
            }

            if ($dryRun) {
                $this->line("Would copy: {$path}");
                $copied++;
                continue;
            }

            File::ensureDirectoryExists(dirname($target));
            if (File::copy($source, $target) && File::exists($target) && hash_file('sha256', $source) === hash_file('sha256', $target)) {
                $copied++;
            } else {
                $failed++;
                $this->error("Failed to copy: {$path}");
            }
        }

        $this->info("Audited paths: {$paths->count()}");
        $this->info("Copied: {$copied}");
        $this->info("Already present/verified: {$already}");
        $this->info("Missing source: {$missingSource}");
        $this->info("Mismatched target: {$mismatched}");
        $this->info("Failed/unsafe: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
