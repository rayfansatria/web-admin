<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class InstitutionSetting extends Model
{
    protected $fillable = ['institution_id','key','value','value_type','description'];

    protected $casts = [
        'value' => 'string',
    ];

    public function getDecodedValueAttribute()
    {
        if ($this->value_type === 'json') return json_decode($this->value, true);
        if ($this->value_type === 'boolean') return filter_var($this->value, FILTER_VALIDATE_BOOLEAN);
        if ($this->value_type === 'integer') return intval($this->value);
        return $this->value;
    }
}