<?php

namespace App\Notifications;

use App\Message;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Log;
use NotificationChannels\OneSignal\OneSignalChannel;
use NotificationChannels\OneSignal\OneSignalMessage;
use NotificationChannels\OneSignal\OneSignalWebButton;

class ChatNotification extends Notification
{
    use Queueable;
    
    private $channel;
    
    private $data;
    private $notif;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($channel,$data)
    {
        //
        $this->channel=$channel;
        $this->data=$data;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return [OneSignalChannel::class];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
                    ->line('The introduction to the notification.')
                    ->action('Notification Action', url('/'))
                    ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }

    /**
     * @param $notifiable
     * @return OneSignalMessage
     */
    public function toOneSignal($notifiable){

        $sub = $this->getSubject();
        $url = env('APP_URL')."chat-rom/download-zip/";
        $k = 'message';

        if($this->channel=='new_message'||$this->channel=='updated_message'){
            $this->notif['id'] = $this->data["message"]->id;
            $this->notif['body'] = $this->data["message"]->body;
            $this->notif['type'] = $this->data["message"]->type;
            $this->notif['status'] = $this->data["message"]->status;
            $this->notif['created_at'] = (new Carbon($this->data["message"]->created_at))->toDateTimeString();
            $this->notif['user_img_profile'] = $this->data["message"]->sender->person->picture;
            $this->notif['user_full_name'] = $this->data["message"]->sender->full_name;
            $this->notif['chat_id'] = $this->data["chat_id"];
            $this->notif['chat_user_id'] = $this->data["message"]->chat_user_id;
            $this->notif['usetoOneSignalr_id'] = $this->data["message"]->sender->id;

            if (isset($this->data["asset_id"])){
                $this->notif['asset_id'] = $this->data["asset_id"];
            }
            if (isset($this->data["file_id"])){
                $this->notif['file_id'] = $this->data["file_id"];
            }
        }elseif ($this->channel=='new_chat'){
            $this->notif['id'] = $this->data["chat"]->id;
            $this->notif['body'] = $this->data["chat"]->title;
        }elseif ($this->channel=='new_community_user'){
            $this->notif['body'] = "Hey!! You have joined new community" . $this->data["community"]->name;
        }else{
            $this->notif['id'] = $this->data["chat"]->id;
            $this->notif['body'] = $this->data["chat"]->title;
        }

        $icon = 'icon';
        return OneSignalMessage::create()
            ->subject($sub)
            ->body($this->notif['body'])
            ->setData('channel', $this->channel)
            ->setData($k, json_encode($this->notif))
            ->icon($icon);
    }

    private function getBody()
    {
        switch ($this->channel) {
            case "new_message":
                return trans('notification.chat.new_message_body');
                break;
            case "updated_message":
                return trans('notification.chat.updated_message_body');
                break;
            case "new_chat":
                return trans('notification.chat.new_chat_body');
                break;
        }
    }

    private function getSubject()
    {
        switch ($this->channel) {
            case "new_message":
                return trans('notification.chat.new_message_subject');
                break;
            case "updated_message":
                return trans('notification.chat.updated_message_subject');
                break;
            case "new_chat":
                return trans('notification.chat.new_chat_subject');
            default:
                return trans("notification.chat.new_message_subject");
                break;
        }
    }
}
