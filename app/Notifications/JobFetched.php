<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class JobFetched extends Notification
{
    use Queueable;
    public $msg = [];

    public function __construct($msg = [])
    {
        $this->msg = $msg;
    }

    public function via($notifiable)
    {
        return [TelegramChannel::class];
    }

    public function toTelegram($notifiable)
    {
        $msg = $this->msg;

        if ($msg['to'] == 'rapid_api') {
            $telegram_id = env('TELEGRAM_RAPID_API_JOBS');
        }

        return TelegramMessage::create()
        // Optional recipient user id.
            ->to($telegram_id)
            ->content($msg['msg']);
    }

}