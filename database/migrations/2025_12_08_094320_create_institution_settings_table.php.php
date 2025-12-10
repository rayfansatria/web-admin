<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInstitutionSettingsTable extends Migration
{
    public function up()
    {
        Schema::create('institution_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->string('key', 150);
            $table->longText('value')->nullable();
            $table->enum('value_type', ['string','integer','boolean','json','date'])->default('string');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['institution_id','key']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('institution_settings');
    }
}