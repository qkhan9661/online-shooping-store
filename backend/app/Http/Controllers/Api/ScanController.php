<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ScanBodyJob;
use App\Models\Scan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ScanController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'height_cm' => ['required', 'numeric', 'min:120', 'max:230'],
            'front_image' => ['required', 'file', 'image', 'max:16384'],
            'side_image' => ['nullable', 'file', 'image', 'max:16384'],
            'right_shoulder_image' => ['nullable', 'file', 'image', 'max:16384'],
            'left_shoulder_image' => ['nullable', 'file', 'image', 'max:16384'],
            'chest_detail_image' => ['nullable', 'file', 'image', 'max:16384'],
        ]);

        $scan = Scan::query()->create([
            'user_id' => $request->user()->id,
            'status' => 'pending',
            'height_cm' => $data['height_cm'],
        ]);

        $dir = 'scans/'.$scan->id;

        $frontExt = $request->file('front_image')->extension();
        $frontPath = $request->file('front_image')->storeAs($dir, 'front.'.$frontExt, 'public');

        $sidePath = $this->storeOptional($request, 'side_image', $dir, 'side');
        $rightPath = $this->storeOptional($request, 'right_shoulder_image', $dir, 'right_shoulder');
        $leftPath = $this->storeOptional($request, 'left_shoulder_image', $dir, 'left_shoulder');
        $chestPath = $this->storeOptional($request, 'chest_detail_image', $dir, 'chest');

        $scan->update([
            'front_image_path' => $frontPath,
            'side_image_path' => $sidePath,
            'right_shoulder_path' => $rightPath,
            'left_shoulder_path' => $leftPath,
            'chest_detail_path' => $chestPath,
        ]);

        ScanBodyJob::dispatch($scan->id);

        return response()->json([
            'message' => 'Scan queued',
            'scan' => $this->transformScan($scan->fresh()),
        ], 202);
    }

    private function storeOptional(Request $request, string $field, string $dir, string $basename): ?string
    {
        if (! $request->hasFile($field)) {
            return null;
        }
        $ext = $request->file($field)->extension();

        return $request->file($field)->storeAs($dir, $basename.'.'.$ext, 'public');
    }

    public function show(Request $request, Scan $scan): JsonResponse
    {
        if ($scan->user_id !== $request->user()->id) {
            abort(404);
        }

        $scan->load(['measurements' => fn ($q) => $q->latest()->limit(1)]);

        return response()->json($this->transformScan($scan));
    }

    private function transformScan(Scan $scan): array
    {
        $disk = Storage::disk('public');
        $url = fn (?string $p) => $p ? $disk->url($p) : null;

        return [
            'id' => $scan->id,
            'status' => $scan->status,
            'height_cm' => (float) $scan->height_cm,
            'ai_confidence' => $scan->ai_confidence !== null ? (float) $scan->ai_confidence : null,
            'error_message' => $scan->error_message,
            'processed_at' => $scan->processed_at?->toIso8601String(),
            'front_image_url' => $url($scan->front_image_path),
            'side_image_url' => $url($scan->side_image_path),
            'right_shoulder_image_url' => $url($scan->right_shoulder_path),
            'left_shoulder_image_url' => $url($scan->left_shoulder_path),
            'chest_detail_image_url' => $url($scan->chest_detail_path),
            'measurement' => $scan->relationLoaded('measurements')
                ? $scan->measurements->first()
                : $scan->measurements()->latest()->first(),
        ];
    }
}
