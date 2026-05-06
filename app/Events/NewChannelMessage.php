<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewChannelMessage implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $channelId;

    public function __construct(array $message, $channelId)
    {
        $this->message = $message;
        $this->channelId = $channelId;
    }

    public function broadcastOn()
    {
        return new Channel('course-channel.' . $this->channelId);
    }
    
    public function broadcastAs()
    {
        return 'message.created';
    }
}
