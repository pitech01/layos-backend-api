<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChannelMessageDeleted implements \Illuminate\Contracts\Broadcasting\ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $messageId;
    public $courseId;

    public function __construct($messageId, $courseId)
    {
        $this->messageId = $messageId;
        $this->courseId = $courseId;
    }

    public function broadcastOn()
    {
        return new Channel('course-channel.' . $this->courseId);
    }

    public function broadcastAs()
    {
        return 'message.deleted';
    }
}
