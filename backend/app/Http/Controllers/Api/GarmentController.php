<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\FitPredictionJob;
use App\Jobs\GenerateFitRecommendationJob;
use App\Jobs\VtonAssetPipelineJob;
use App\Jobs\VtonGpuJob;
use App\Models\FitRecommendation;
use App\Models\Garment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GarmentController extends Controller
{
    public function index(): JsonResponse
    {
        $items = Garment::query()->orderBy('name')->paginate(24);

        return response()->json($items);
    }

    public function show(Request $request, Garment $garment): JsonResponse
    {
        $fit = FitRecommendation::query()
            ->where('user_id', $request->user()->id)
            ->where('garment_id', $garment->id)
            ->first();

        return response()->json($this->transformGarment($garment, $fit));
    }

    public function requestRecommendation(Request $request, Garment $garment): JsonResponse
    {
        $row = FitRecommendation::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'garment_id' => $garment->id,
            ],
            [
                'status' => 'pending',
                'recommended_size' => null,
                'fit_notes' => null,
                'confidence' => null,
                'measurement_snapshot' => null,
            ],
        );

        GenerateFitRecommendationJob::dispatch($row->id);

        return response()->json([
            'message' => 'Fit recommendation queued (size chart); listen for FitUpdated kind size_chart',
            'fit_recommendation' => $row->fresh(),
        ], 202);
    }

    /**
     * Queue GPU /infer/fit (heatmap-style / ML fit). Broadcasts FitUpdated (kind gpu_fit).
     */
    public function requestGpuFit(Request $request, Garment $garment): JsonResponse
    {
        FitPredictionJob::dispatch($request->user()->id, $garment->id);

        return response()->json([
            'message' => 'GPU fit prediction queued',
            'garment_id' => $garment->id,
            'queue' => 'vton-gpu',
        ], 202);
    }

    /**
     * Queue GPU /infer/tryon. Broadcasts VtonProgress on private user.{id}.cloth.
     */
    public function requestVtonTryOn(Request $request, Garment $garment): JsonResponse
    {
        $data = $request->validate([
            'scan_id' => ['nullable', 'integer', 'min:1'],
        ]);

        VtonGpuJob::dispatch(
            $request->user()->id,
            $garment->id,
            $data['scan_id'] ?? null,
        );

        return response()->json([
            'message' => 'VTON try-on inference queued',
            'garment_id' => $garment->id,
            'queue' => 'vton-gpu',
        ], 202);
    }

    public function fitRecommendation(Request $request, Garment $garment): JsonResponse
    {
        $row = FitRecommendation::query()
            ->where('user_id', $request->user()->id)
            ->where('garment_id', $garment->id)
            ->first();

        if (! $row) {
            return response()->json(['message' => 'No recommendation yet'], 404);
        }

        return response()->json($row);
    }

    /**
     * Queue asset pipeline: model.glb + physics_profile.json to configured disk (S3 in production).
     */
    public function queueVtonPipeline(Request $request, Garment $garment): JsonResponse
    {
        VtonAssetPipelineJob::dispatch($garment->id, $request->user()->id);

        return response()->json([
            'message' => 'VTON asset pipeline queued',
            'garment_id' => $garment->id,
            'queue' => 'assets',
        ], 202);
    }

    private function transformGarment(Garment $garment, ?FitRecommendation $fit): array
    {
        $public = Storage::disk('public');
        $assetDisk = Storage::disk(config('filesystems.default', 'public'));
        $imageUrl = $garment->image_path ? $public->url($garment->image_path) : null;
        $meshUrl = $garment->mesh_glb_path ? $assetDisk->url($garment->mesh_glb_path) : null;
        $meshLodUrl = $garment->mesh_ar_lod_path ? $assetDisk->url($garment->mesh_ar_lod_path) : null;

        return [
            'id' => $garment->id,
            'sku' => $garment->sku,
            'name' => $garment->name,
            'description' => $garment->description,
            'price' => (float) $garment->price,
            'image_url' => $imageUrl,
            'size_chart' => $garment->size_chart,
            'fit_recommendation' => $fit,
            'vton' => [
                'asset_pipeline_status' => $garment->asset_pipeline_status,
                'mesh_glb_url' => $meshUrl,
                'mesh_ar_lod_url' => $meshLodUrl,
                'mesh_cdn_url' => $garment->mesh_cdn_url,
                'physics_profile' => $garment->physics_profile,
            ],
        ];
    }
}
