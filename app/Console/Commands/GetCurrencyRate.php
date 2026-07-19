<?php

namespace App\Console\Commands;

use App\Libraries\CurrencyLayer;
use Illuminate\Console\Command;

class GetCurrencyRate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'currency:rate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Getting currency on a outbound api';

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
        $cl = new CurrencyLayer();
        $cl->get_currencies_rate();
    }
}
