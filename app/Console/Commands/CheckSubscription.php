<?php

namespace App\Console\Commands;

use App\SubscriptionUser;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckSubscription extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:subscription';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check user subscription validaty';

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
        $subs = SubscriptionUser::whereDate('expiration_date','<',Carbon::today())->whereIsValid(true)->get();
//        $subs->update(['is_valid'=>false]);
        foreach ($subs as $sub){
            if($subs->aut_renew){
                // try to auto proccess subscription bill here
            }else{
                $sub->is_valid=false;
                $sub->save();
                // apply penalites to user rate and status
            }
        }
    }
}
