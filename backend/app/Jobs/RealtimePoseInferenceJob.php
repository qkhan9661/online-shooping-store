<?php

namespace App\Jobs;

use App\Events\PoseUpdated;
use App\Services\GpuInferenceClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Async realtime pose: GPU /infer/pose (JSON body). Clients subscribe via WebSocket (PoseUpdated).
 */
class RealtimePoseInferenceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 45;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public int $userId, public array $payload)
    {
        $this->onQueue('default');
    }

    public function handle(GpuInferenceClient $gpu): void
    {
        $body = array_merge($this->payload, ['user_id' => $this->userId]);

        try {
            $out = $gpu->inferPoseJson($body);
            PoseUpdated::dispatch($this->userId, 'realtime', $out);
        } catch (Throwable $e) {
            PoseUpdated::dispatch($this->userId, 'realtime', [
                'error' => $e->getMessage(),
                'mode' => 'failed',
            ]);
        }
    }
}
