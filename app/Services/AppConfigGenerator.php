<?php
namespace App\Services;

use App\Models\Institution;
use App\Models\InstitutionSetting;

class AppConfigGenerator
{
    public function generateForInstitution(int $institutionId): array
    {
        $inst = Institution::findOrFail($institutionId);
        $settingsRows = InstitutionSetting::where('institution_id', $institutionId)->get();
        $settings = [];
        foreach ($settingsRows as $row) {
            $val = $row->value;
            $json = json_decode($val, true);
            $settings[$row->key] = $json === null ? $val : $json;
        }

        $features = [
            'attendance' => [
                'enabled' => ($settings['features.attendance.enabled'] ?? 'true') === true || ($settings['features.attendance.enabled'] ?? 'true') === 'true',
                'allow_mobile' => ($settings['attendance.allow_mobile'] ?? true),
                'require_photo' => ($settings['attendance.require_photo'] ?? false),
                'require_location' => ($settings['attendance.require_location'] ?? false),
                'liveness_detection' => ($settings['attendance.liveness_detection'] ?? false),
            ],
            'timesheet' => ['enabled' => ($settings['features.timesheet.enabled'] ?? false)],
            'overtime' => [
                'enabled' => ($settings['features.overtime.enabled'] ?? false),
                'require_approval' => ($settings['overtime.require_approval'] ?? true),
            ],
        ];

        $appConfig = [
            'appId' => 'com.example.absensi.' . strtolower($inst->code),
            'displayName' => $inst->name,
            'institution' => ['id'=>$inst->id,'code'=>$inst->code,'name'=>$inst->name],
            'features' => $features,
            'branding' => [
                'primaryColor' => $settings['branding.primaryColor'] ?? '#3B82F6',
                'logoUrl' => $settings['branding.logoUrl'] ?? $inst->logo_url,
            ],
            'build' => [
                'platforms'=> [$settings['build.platform'] ?? 'android'],
                'bundleIdentifier' => 'com.example.absensi.' . strtolower($inst->code),
                'version' => $settings['build.version'] ?? '1.0.0'
            ],
            'generated_at' => now()->toDateTimeString(),
        ];

        return $appConfig;
    }
}