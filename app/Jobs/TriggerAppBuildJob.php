<?php

namespace App\Jobs;

use App\Models\AppBuild;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class TriggerAppBuildJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $buildId;

    public function __construct(int $buildId)
    {
        $this->buildId = $buildId;
    }

    public function handle(): void
    {
        $build = AppBuild::findOrFail($this->buildId);

        // Mulai: tandai running
        $build->status = 'running';
        $build->save();

        try {
            // TODO: ganti dengan proses build APK sebenarnya.
            // Stub/dev: buat file dummy di storage publik.
            $filename = "apks/{$build->id}.apk";
            Storage::disk('public')->put($filename, 'APK content placeholder');

            // URL publik (butuh `php artisan storage:link` dan APP_URL benar)
            $apkUrl = Storage::disk('public')->url($filename); // ex: http://localhost:8000/storage/apks/{id}.apk

            $build->apk_url = $apkUrl;
            $build->status = 'success';
            $build->save();
        } catch (\Throwable $e) {
            $build->status = 'failed';
            if ($build->isFillable('error_log') || $build->getAttribute('error_log') !== null) {
                $build->error_log = $e->getMessage();
            }
            $build->save();
            report($e);
        }
    }
}