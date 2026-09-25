<?php

namespace App\Enums;

enum MessageStatus: string
{
    case Received = 'received'; // incoming, stored
    case Pending = 'pending';   // outgoing, queued for send
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';   // bot decided not to reply / not sent
}
