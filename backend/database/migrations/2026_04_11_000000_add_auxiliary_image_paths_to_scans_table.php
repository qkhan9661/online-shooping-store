<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scans', function (Blueprint $table) {
            $table->string('right_shoulder_path')->nullable()->after('side_image_path');
            $table->string('left_shoulder_path')->nullable()->after('right_shoulder_path');
            $table->string('chest_detail_path')->nullable()->after('left_shoulder_path');
        });
    }

    public function down(): void
    {
        Schema::table('scans', function (Blueprint $table) {
            $table->dropColumn(['right_shoulder_path', 'left_shoulder_path', 'chest_detail_path']);
        });
    }
};
