<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProjectChatMessageCreated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly int $projectId,
        public readonly int $chatSessionId,
        public readonly array $payload,
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel(sprintf('project.%d.chat.%d', $this->projectId, $this->chatSessionId))];
    }

    public function broadcastAs(): string
    {
        return 'chat.message.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}

