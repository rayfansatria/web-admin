<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAppBuildsTable extends Migration
{
    public function up()
    {
        Schema::create('app_builds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->json('config_snapshot_json')->nullable();
            $table->enum('status', ['pending','queued','processing','success','failed'])->default('pending')->index();
            $table->string('platform')->nullable(); // 'android','ios','both'
            $table->string('artifact_url')->nullable();
            $table->text('build_log')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('app_builds');
    }
}