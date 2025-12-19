<?php

namespace App\Jobs;

use App\Models\AppBuild;
use App\Services\FlutterAppBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class TriggerAppBuildJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $buildId;
    public int $tries = 1; // Only try once
    public int $timeout = 900; // 15 minutes timeout

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
            Log::info("Starting Flutter APK build for build ID: {$this->buildId}");

            // Get config from build snapshot
            $config = $build->config_snapshot_json;
            
            if (empty($config)) {
                throw new \Exception("Config snapshot is empty");
            }

            // Build APK using Flutter
            $builder = new FlutterAppBuilder();
            $result = $builder->buildApk($this->buildId, $config);

            if (!$result['success']) {
                throw new \Exception($result['error'] ?? 'Build failed');
            }

            // Generate public URL
            $apkUrl = Storage::disk('public')->url("apks/{$this->buildId}.apk");

            $build->apk_url = $apkUrl;
            $build->status = 'success';
            $build->save();

            Log::info("Flutter APK build completed successfully", [
                'build_id' => $this->buildId,
                'apk_url' => $apkUrl
            ]);

        } catch (\Throwable $e) {
            Log::error("Flutter APK build failed", [
                'build_id' => $this->buildId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $build->status = 'failed';
            if ($build->isFillable('error_log') || $build->getAttribute('error_log') !== null) {
                $build->error_log = $e->getMessage();
            }
            $build->save();
            report($e);
        }
    }
}