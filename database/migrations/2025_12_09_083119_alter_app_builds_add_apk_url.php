<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('app_builds', function (Blueprint $table) {
            $table->string('apk_url', 255)->nullable()->after('status');
            $table->string('status', 20)->default('pending')->change();
        });
    }

    public function down(): void
    {
        Schema::table('app_builds', function (Blueprint $table) {
            $table->dropColumn('apk_url');
            // jika mau balikin status ke tipe lama, sesuaikan:
            // $table->boolean('status')->default(0)->change();
        });
    }
};