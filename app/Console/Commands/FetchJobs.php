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

        /**
         * Country: HU
        */
        $body = [
            'dateCreated' => now()->subDays(4)->format('Y-m-d'),
            // 'countryCode' => 'HU',
        ];

        $categories = [
            'construction',
            'logistics',
            'transport',
        ];

        foreach($categories as $category){
            $body['industry'] = $category;
            $rapid_api->call($body);
        }

        dump('Jobs Fetched');
    }


}
