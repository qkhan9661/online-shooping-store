<?php

namespace App\Jobs;

use App\Events\VtonProgress;
use App\Models\Garment;
use App\Models\Scan;
use App\Models\User;
use App\Services\GpuInferenceClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Runtime virtual try-on: GPU /infer/tryon. Results should be consumed by AR clients via cloth channel.
 */
class VtonGpuJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public int $userId,
        public int $garmentId,
        public ?int $scanId = null,
        public array $extra = [],
    ) {
        $this->onQueue('vton-gpu');
    }

    public function handle(GpuInferenceClient $gpu): void
    {
        if (! User::query()->whereKey($this->userId)->exists() || ! Garment::query()->whereKey($this->garmentId)->exists()) {
            return;
        }

        VtonProgress::dispatch($this->userId, $this->garmentId, 'tryon_queued', 5, []);

        $payload = array_merge([
            'user_id' => $this->userId,
            'garment_id' => $this->garmentId,
            'scan_id' => $this->scanId,
        ], $this->extra);

        if ($this->scanId !== null) {
            $scan = Scan::query()->find($this->scanId);
            if ($scan && $scan->user_id === $this->userId) {
                $payload['scan_id'] = $scan->id;
            }
        }

        try {
            VtonProgress::dispatch($this->userId, $this->garmentId, 'tryon_inference', 40, []);
            $result = $gpu->inferTryon($payload);
            VtonProgress::dispatch($this->userId, $this->garmentId, 'tryon_complete', 100, [
                'result' => $result,
            ]);
        } catch (Throwable $e) {
            VtonProgress::dispatch($this->userId, $this->garmentId, 'tryon_failed', 100, [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
