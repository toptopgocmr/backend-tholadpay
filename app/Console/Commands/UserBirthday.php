<?php

namespace App\Console\Commands;

use App\Notifications\BirthdayNotification;
use App\Person;
use App\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Mail;

class UserBirthday extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:birthday';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send birthday message to peexer ';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //
        $users = User::whereHas('person',function($q){
            $q->whereMonth('birth_date','=',date('m'))
                ->whereDay('birth_date','=',date('d'));
        })->get();


        Notification::send($users,new BirthdayNotification());

        $this->info('The happy birthday  email were sent successfully!');
    }






}
