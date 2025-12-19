<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\File;

class FlutterAppBuilder
{
    private string $templatePath;
    private string $buildPath;
    private string $flutterCommand;

    public function __construct()
    {
        // Path ke Flutter template project (harus sudah exist)
        $this->templatePath = env('FLUTTER_TEMPLATE_PATH', base_path('../flutter-template'));
        
        // Path untuk build workspace (temporary)
        $this->buildPath = storage_path('app/flutter-builds');
        
        // Flutter command (adjust for Windows). Coba resolve otomatis jika tidak absolute.
        $configured = env('FLUTTER_CMD', 'flutter');
        $this->flutterCommand = $this->resolveFlutterCommand($configured);
    }

    private function resolveFlutterCommand(string $configured): string
    {
        try {
            // Jika path yang dikonfigurasi langsung ada, pakai itu
            if (\Illuminate\Support\Str::endsWith(strtolower($configured), '.bat') || \Illuminate\Support\Str::contains($configured, '\\') || \Illuminate\Support\Str::contains($configured, '/')) {
                if (\Illuminate\Support\Facades\File::exists($configured)) {
                    return $configured;
                }
            } else {
                // Coba cari via `where flutter.bat` (Windows) atau `which flutter` (Unix)
                $finder = strtoupper(PHP_OS_FAMILY) === 'Windows' ? 'where flutter.bat' : 'which flutter';
                $res = \Illuminate\Support\Facades\Process::run($finder);
                $path = trim($res->output());
                if ($res->successful() && $path !== '') {
                    // Ambil baris pertama jika ada banyak
                    $lines = preg_split('/\r?\n/', $path);
                    if (!empty($lines[0]) && \Illuminate\Support\Facades\File::exists($lines[0])) {
                        return $lines[0];
                    }
                }
            }

            // Coba dari FLUTTER_HOME
            $home = env('FLUTTER_HOME');
            if ($home) {
                $candidate = rtrim($home, '\\/') . (strtoupper(PHP_OS_FAMILY) === 'Windows' ? '/bin/flutter.bat' : '/bin/flutter');
                if (\Illuminate\Support\Facades\File::exists($candidate)) {
                    return $candidate;
                }
            }

            // Coba beberapa lokasi umum di Windows
            if (strtoupper(PHP_OS_FAMILY) === 'Windows') {
                $candidates = [
                    'C:/src/flutter/bin/flutter.bat',
                    'C:/flutter/bin/flutter.bat',
                ];
                foreach ($candidates as $c) {
                    if (\Illuminate\Support\Facades\File::exists($c)) return $c;
                }
            }

            // Fallback ke nilai yang dikonfigurasi (bisa 'flutter')
            return $configured;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal resolve FLUTTER_CMD, fallback ke nilai konfigurasi', [
                'configured' => $configured,
                'error' => $e->getMessage(),
            ]);
            return $configured;
        }
    }

    /**
     * Build APK dari config institution
     * 
     * @param int $buildId
     * @param array $config Generated config dari AppConfigGenerator
     * @return array ['success' => bool, 'apk_path' => string|null, 'error' => string|null]
     */
    public function buildApk(int $buildId, array $config): array
    {
        try {
            // 1. Create working directory
            $workDir = $this->buildPath . "/build-{$buildId}";
            
            if (File::exists($workDir)) {
                File::deleteDirectory($workDir);
            }
            File::makeDirectory($workDir, 0755, true);

            // 2. Copy template ke work directory
            if (!File::exists($this->templatePath)) {
                return [
                    'success' => false,
                    'apk_path' => null,
                    'error' => "Flutter template tidak ditemukan di: {$this->templatePath}. Jalankan: flutter create {$this->templatePath}"
                ];
            }

            File::copyDirectory($this->templatePath, $workDir);
            Log::info("Template copied to: {$workDir}");

            // 3. Customize Flutter project
            $this->customizeProject($workDir, $config);

            // 4. Get dependencies
            $this->runFlutterCommand($workDir, 'pub get');

            // 5. Build APK
            $this->runFlutterCommand($workDir, 'build apk --release');

            // 6. Locate built APK
            $apkPath = "{$workDir}/build/app/outputs/flutter-apk/app-release.apk";
            
            if (!File::exists($apkPath)) {
                return [
                    'success' => false,
                    'apk_path' => null,
                    'error' => "APK build selesai tapi file tidak ditemukan di: {$apkPath}"
                ];
            }

            // 7. Copy APK ke storage/app/public/apks
            $finalApkDir = storage_path('app/public/apks');
            File::ensureDirectoryExists($finalApkDir);
            
            $finalApkPath = "{$finalApkDir}/{$buildId}.apk";
            File::copy($apkPath, $finalApkPath);
            
            Log::info("APK built successfully: {$finalApkPath}");

            // 8. Cleanup work directory (optional, comment jika ingin debug)
            File::deleteDirectory($workDir);

            return [
                'success' => true,
                'apk_path' => $finalApkPath,
                'error' => null
            ];

        } catch (\Throwable $e) {
            Log::error("Flutter build error: " . $e->getMessage(), [
                'buildId' => $buildId,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'apk_path' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Customize Flutter project dengan config
     */
    private function customizeProject(string $projectPath, array $config): void
    {
        $institution = $config['institution'] ?? [];
        $branding = $config['branding'] ?? [];
        $features = $config['features'] ?? [];
        $build = $config['build'] ?? [];

        // 1. Update pubspec.yaml (app name & version)
        $this->updatePubspec($projectPath, [
            'name' => $this->sanitizePackageName($institution['code'] ?? 'app'),
            'description' => $institution['name'] ?? 'Custom App',
            'version' => $build['version'] ?? '1.0.0',
        ]);

        // 2. Update AndroidManifest.xml (package name & app label)
        $this->updateAndroidManifest($projectPath, [
            'package' => $build['bundleIdentifier'] ?? 'com.example.app',
            'label' => $institution['name'] ?? 'App',
        ]);

        // 3. Generate config.json untuk runtime config
        $this->generateConfigJson($projectPath, $config);

        // 4. Update app colors (optional - requires code generation)
        $this->updateAppColors($projectPath, $branding);

        Log::info("Project customized", ['institution' => $institution['code'] ?? 'unknown']);
    }

    /**
     * Update pubspec.yaml
     */
    private function updatePubspec(string $projectPath, array $data): void
    {
        $pubspecPath = "{$projectPath}/pubspec.yaml";
        
        if (!File::exists($pubspecPath)) {
            Log::warning("pubspec.yaml not found at: {$pubspecPath}");
            return;
        }

        $content = File::get($pubspecPath);
        
        // Simple replacement (for production, use YAML parser)
        $content = preg_replace('/^name:\s+.+$/m', "name: {$data['name']}", $content);
        $content = preg_replace('/^description:\s+.+$/m', "description: {$data['description']}", $content);
        $content = preg_replace('/^version:\s+.+$/m', "version: {$data['version']}+1", $content);
        
        File::put($pubspecPath, $content);
    }

    /**
     * Update Android AndroidManifest.xml
     */
    private function updateAndroidManifest(string $projectPath, array $data): void
    {
        $manifestPath = "{$projectPath}/android/app/src/main/AndroidManifest.xml";
        
        if (!File::exists($manifestPath)) {
            Log::warning("AndroidManifest.xml not found");
            return;
        }

        $content = File::get($manifestPath);
        
        // Update package
        $content = preg_replace('/package="[^"]*"/', 'package="' . $data['package'] . '"', $content);
        
        // Update android:label
        $content = preg_replace('/android:label="[^"]*"/', 'android:label="' . $data['label'] . '"', $content);
        
        File::put($manifestPath, $content);

        // Also update build.gradle applicationId
        $buildGradlePath = "{$projectPath}/android/app/build.gradle";
        if (File::exists($buildGradlePath)) {
            $gradleContent = File::get($buildGradlePath);
            $gradleContent = preg_replace('/applicationId\s+"[^"]*"/', 'applicationId "' . $data['package'] . '"', $gradleContent);
            File::put($buildGradlePath, $gradleContent);
        }
    }

    /**
     * Generate assets/config.json untuk runtime
     */
    private function generateConfigJson(string $projectPath, array $config): void
    {
        $assetsPath = "{$projectPath}/assets";
        File::ensureDirectoryExists($assetsPath);
        
        $configPath = "{$assetsPath}/config.json";
        File::put($configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        // Ensure assets are declared in pubspec.yaml
        $this->ensureAssetsInPubspec($projectPath);
    }

    /**
     * Update app colors di lib/theme.dart atau constants
     */
    private function updateAppColors(string $projectPath, array $branding): void
    {
        $themePath = "{$projectPath}/lib/theme/app_colors.dart";
        
        if (!File::exists($themePath)) {
            // Create basic theme file
            $colorContent = $this->generateColorsDart($branding);
            File::ensureDirectoryExists(dirname($themePath));
            File::put($themePath, $colorContent);
        }
    }

    private function generateColorsDart(array $branding): string
    {
        $primaryColor = $branding['primaryColor'] ?? '#3B82F6';
        $secondaryColor = $branding['secondaryColor'] ?? '#10B981';
        
        return <<<DART
import 'package:flutter/material.dart';

class AppColors {
  static const Color primary = Color(0x{$this->hexToFlutterColor($primaryColor)});
  static const Color secondary = Color(0x{$this->hexToFlutterColor($secondaryColor)});
}
DART;
    }

    private function hexToFlutterColor(string $hex): string
    {
        $hex = ltrim($hex, '#');
        return 'FF' . strtoupper($hex);
    }

    private function ensureAssetsInPubspec(string $projectPath): void
    {
        $pubspecPath = "{$projectPath}/pubspec.yaml";
        $content = File::get($pubspecPath);
        
        // Check if assets already declared
        if (!str_contains($content, 'assets:') || !str_contains($content, '- assets/')) {
            // Add assets section
            $content = preg_replace(
                '/(flutter:\s*\n)/m',
                "$1  assets:\n    - assets/\n",
                $content
            );
            File::put($pubspecPath, $content);
        }
    }

    /**
     * Run flutter command
     */
    private function runFlutterCommand(string $workDir, string $command): void
    {
        $exe = $this->flutterCommand;
        // Quote executable path in case it contains spaces
        $exeQuoted = '"' . $exe . '"';

        // On Windows, prefer using `cmd` with /d to allow drive changes
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' || PHP_OS_FAMILY === 'Windows';
        $cd = $isWindows ? 'cd /d' : 'cd';

        $fullCommand = "$cd \"$workDir\" && $exeQuoted $command";

        Log::info("Running: {$fullCommand}");

        $result = Process::timeout(900) // up to 15 minutes
            ->run($fullCommand);

        if (!$result->successful()) {
            throw new \RuntimeException(
                "Flutter command failed: {$command}\nOutput: {$result->output()}\nError: {$result->errorOutput()}"
            );
        }

        Log::info("Command output: " . $result->output());
    }

    private function sanitizePackageName(string $name): string
    {
        return strtolower(preg_replace('/[^a-z0-9_]/', '', $name));
    }
}
