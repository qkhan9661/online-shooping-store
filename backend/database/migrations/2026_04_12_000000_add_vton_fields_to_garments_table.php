<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('garments', function (Blueprint $table) {
            $table->string('mesh_glb_path')->nullable()->after('image_path');
            $table->string('mesh_ar_lod_path')->nullable()->after('mesh_glb_path');
            $table->json('physics_profile')->nullable()->after('mesh_ar_lod_path');
            $table->string('asset_pipeline_status')->default('pending')->after('physics_profile');
            $table->string('mesh_cdn_url')->nullable()->after('asset_pipeline_status');
        });
    }

    public function down(): void
    {
        Schema::table('garments', function (Blueprint $table) {
            $table->dropColumn([
                'mesh_glb_path',
                'mesh_ar_lod_path',
                'physics_profile',
                'asset_pipeline_status',
                'mesh_cdn_url',
            ]);
        });
    }
};
