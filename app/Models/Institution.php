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
}