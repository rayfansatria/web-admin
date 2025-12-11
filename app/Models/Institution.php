<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Institution extends Model
{
    protected $fillable = ['code','name','timezone','logo_url','is_active'];

    public function settings()
    {
        return $this->hasMany(InstitutionSetting::class);
    }

    /**
     * Get settings as key->value map with decoded values
     */
    public function settingsKeyValue()
    {
        return $this->settings()->get()->mapWithKeys(function ($item) {
            $val = json_decode($item->value, true);
            return [$item->key => $val === null ? $item->value : $val];
        });
    }

    /**
     * Return default nested feature structure
     */
    public function defaultFeaturesStructured()
    {
        return [
            'features' => [
                'attendance' => true,
                'schedule' => true,
                'grades' => true,
                'announcements' => true,
                'messaging' => false,
            ],
            'attendance' => [
                'qr_code' => true,
                'geolocation' => false,
                'face_recognition' => false,
            ],
        ];
    }

    /**
     * Build nested features array by parsing institution_settings keys
     * Supports prefixes "features." and "attendance."
     */
    public function features()
    {
        $settings = $this->settingsKeyValue();
        $defaults = $this->defaultFeaturesStructured();
        
        $result = $defaults;

        foreach ($settings as $key => $value) {
            // Parse keys like "features.attendance" or "attendance.qr_code"
            if (str_starts_with($key, 'features.')) {
                $subKey = substr($key, strlen('features.'));
                $result['features'][$subKey] = $this->normalizeBoolean($value);
            } elseif (str_starts_with($key, 'attendance.')) {
                $subKey = substr($key, strlen('attendance.'));
                $result['attendance'][$subKey] = $this->normalizeBoolean($value);
            }
        }

        return $result;
    }

    /**
     * Normalize boolean-like strings to actual booleans
     */
    private function normalizeBoolean($value)
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            $lower = strtolower($value);
            if (in_array($lower, ['true', '1', 'yes', 'on'])) {
                return true;
            }
            if (in_array($lower, ['false', '0', 'no', 'off', ''])) {
                return false;
            }
        }
        return (bool) $value;
    }
}