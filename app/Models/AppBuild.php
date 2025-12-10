<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AppBuild extends Model
{
    protected $fillable = ['institution_id','config_snapshot_json','status','platform','artifact_url','build_log','finished_at'];

    protected $casts = [
        'config_snapshot_json' => 'array',
        'finished_at' => 'datetime',
    ];
    protected $guarded = [];
}