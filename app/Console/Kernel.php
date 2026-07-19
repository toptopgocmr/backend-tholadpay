<?php

namespace App\Console;

use App\Console\Commands\BackfillCustomerRoles;
use App\Console\Commands\CheckSubscription;
use App\Console\Commands\GetCurrencyRate;
use App\Console\Commands\UserBirthday;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
        GetCurrencyRate::class,
        UserBirthday::class,
        CheckSubscription::class,
        BackfillCustomerRoles::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('inspire')->hourly();
        $schedule->command('user:birthday')->daily();
        $schedule->command('check:subscription')->daily();
        $schedule->command('currency:rate')->cron('1 6 * * *');
    }

    /**
     * Register the Closure based commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        require base_path('routes/console.php');
    }
}
