<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\RapidAPI;
use App\Http\Controllers\RapidAPIController;

class FetchJobs extends Command
{

    protected $signature = 'rapidapi:fetch-jobs';

    protected $description = '';

     public function handle()
     {
        $rapid_api = new RapidAPIController();

        $body = [
            'dateCreated' => now()->subDays(4)->format('Y-m-d'),
            'countryCode' => 'HU',
        ];


        $body['industry'] = "construction";
        $rapid_api->call($body);

        $body['industry'] = "logistics";
        //$rapid_api->call($body);

        $body['industry'] = "transport";
        //$rapid_api->call($body);

        dump('Jobs Fetched');
    }


}