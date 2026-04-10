<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Asset pipeline or try-on job progress (queued broadcast — safe for heavier payloads).
 */
class VtonProgress implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public int $garmentId,
        public string $stage,
        public int $progressPercent,
        public array $meta = [],
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->userId.'.cloth')];
    }

    public function broadcastAs(): string
    {
        return 'vton.progress';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'garment_id' => $this->garmentId,
            'stage' => $this->stage,
            'progress_percent' => $this->progressPercent,
            'meta' => $this->meta,
        ];
    }
}
