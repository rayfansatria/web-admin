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
     * Return settings as associative array: key => value (json decoded when applicable).
     */
    public function settingsKeyValue(): array
    {
        $rows = $this->settings()->get();
        $map = $rows->mapWithKeys(function ($item) {
            $val = json_decode($item->value, true);
            return [$item->key => ($val === null ? $item->value : $val)];
        })->toArray();

        return $map;
    }

    /**
     * Default structured features (used as fallback/merge).
     */
    public static function defaultFeaturesStructured(): array
    {
        return [
            'attendance' => [
                'allow_mobile' => true,
                'require_photo' => false,
                'liveness_detection' => false,
                'shift' => false,
            ],
            // tambahkan kategori fitur lain bila perlu
        ];
    }

    /**
     * Build a nested features array from settings keys:
     * - keys starting with 'features.' (features.xxx.yyy) will be nested under 'features'
     * - keys starting with 'attendance.' will be nested under 'attendance'
     *
     * Returns merged result between defaults and stored settings.
     */
    public function features(): array
    {
        $s = $this->settingsKeyValue();
        $features = [];

        // helper buat set nested value
        $setNested = function (&$arr, $keys, $value) use (&$setNested) {
            $key = array_shift($keys);
            if ($key === null) return;
            if (count($keys) === 0) {
                // normalize boolean-like strings
                if (is_string($value) && in_array(strtolower($value), ['true', 'false', '1', '0'], true)) {
                    $valLower = strtolower($value);
                    $arr[$key] = ($valLower === 'true' || $valLower === '1');
                } else {
                    $arr[$key] = $value;
                }
                return;
            }
            if (!isset($arr[$key]) || !is_array($arr[$key])) {
                $arr[$key] = [];
            }
            $setNested($arr[$key], $keys, $value);
        };

        foreach ($s as $key => $val) {
            if (strpos($key, 'features.') === 0) {
                $sub = substr($key, strlen('features.'));
                $parts = explode('.', $sub);
                $setNested($features, $parts, $val);
            } elseif (strpos($key, 'attendance.') === 0) {
                $sub = substr($key, strlen('attendance.'));
                $parts = $parts = array_merge(['attendance'], explode('.', $sub));
                $setNested($features, $parts, $val);
            }
            // jika ada pattern lain yang ingin dimasukkan, tambahkan di sini
        }

        // Merge defaults: ensure default structure exists and saved settings override defaults
        $defaults = self::defaultFeaturesStructured();
        $merged = array_replace_recursive($defaults, $features);

        return $merged;
    }
}