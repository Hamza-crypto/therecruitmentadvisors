<?php

namespace App\Http\Controllers;

use App\Models\Job;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use App\Models\RapidAPI;
use App\Notifications\JobFetched;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RapidAPIController extends Controller
{
    public function call($body)
    {
        $defaultValues = [
            'workType' => 'full-time',
            'contractType' => 'permanent'
        ];

        $queryParams = array_merge($body, $defaultValues);
        $queryString = http_build_query($queryParams);

        $url = sprintf("%s?%s", env('RAPID_API_URL'), $queryString);

        $response = Http::withHeaders([
            'X-RapidAPI-Host' => 'daily-international-job-postings.p.rapidapi.com',
            'X-RapidAPI-Key' => env('RAPID_API_KEY'),
        ])->get($url);

        RapidAPI::query()->increment('count');

        $this->store_jobs($response->json(), $body);
    }

    public function store_jobs($jobs, $body)
    {
        $msg['to'] = "rapid_api";
        $msg['msg'] = implode(', ', $body) . "\n";

        $categories = [
            'construction' => 59,
            'Construction' => 59,
        ];

        foreach ($jobs['result'] as $key => $job) {

            $title = $job['title'] ?? '';

            $data = [
                'job_number' => $job['jsonLD']['identifier'] ?? '',
                'job_id' => 0,
                'user_id' => 1465,
                'admin_id' => 1,
                'type' => 'bronze',
                'title' => $title,
                'category_id' => $categories[$job['industry'] ?? 'construction'] ?? 0 ,
                'description' => sprintf("%s", $job['jsonLD']['description']),
                'company_name' => $job['jsonLD']['hiringOrganization']['name'] ?? '',
                'work_type' => 1, // Full Time
                'slug' => Str::slug($title),
                'status' => 1,
                'created' => $this->transformTime($job['dateCreated']),
                'modified' => $this->transformTime($job['dateCreated']),
                'expire_time' => strtotime($job['jsonLD']['validThrough'] ?? ''),
                'payment_type' => 2,
                'role' => "",
                'contact_name' => 'N/A',
                'contact_number' => 'N/A',
                'vacancy' => 0,
                'state_id' => 0,
                'city_id' => 0,
                'postal_code' => '',
                'url' => $job['jsonLD']['url'] ?? '',
                'job_city' => $job['jsonLD']['jobLocation']['address']['addressLocality'] ?? $job['jsonLD']['jobLocation']['name'] ?? '',
                'address' => '',
                'youtube_link' => '',
                'lastdate' => "2024-03-06",
                'youtube_link' => '',
                'selling_point1' => '',
                'selling_point2' => '',
                'selling_point3' => '',
                'hot_job_time' => strtotime($job['dateCreated'] ?? ''),
                'search_count' => 0,
                'view_count' => 0,
                'job_logo_check' => 0,
                'invoice_inumber' => '',
                'payment_type' => 2,
                'weekly_email_sent' => 0,
                'exp_year' => 0,
                'exp_month' => 0,
                'min_exp' => 0,
                'max_exp' => 0,
                'min_salary' => $job['jsonLD']['baseSalary']['value']['minValue'] ?? 0,
                'max_salary' => $job['jsonLD']['baseSalary']['value']['minValue'] ?? 0,
                'confidential' => '',
                'job_position' => '',
                'eligibility' => '',
                'job_salary' => '',
                'job_experience_id' => '',
                'job_email' => '',
                'job_fax' => '',
                'designation_ofperson' => '',
                'brief_abtcomp' => '',
                'meta_tag_title' => '',
                'meta_tag_keywords' => '',
                'meta_tag_description' => '',
                'meta_tag_keyphrase' => '',
                'l_status' => 0,
                'l_skill' => 0,
                'l_exp' => 0,
                'lat' => '',
                'long' => '',
                'feed_id' => null,
                'apply_url' => null,
            ];

            $msg['msg'] .= sprintf("%d. %s", $key+1, $title)  . "\n";
            $existingJob = DB::table('tbl_jobs')
                ->where('job_number', $job['jsonLD']['identifier'])
                ->first();

            // Insert only if the job number doesn't exist in the database
            if (!$existingJob) {
                DB::table('tbl_jobs')->insert($data);
            }
        }

        if(count($jobs['result']) == 0){
            $msg['msg'] .= sprintf("No jobs found")  . "\n";
        }
        Notification::route(TelegramChannel::class, '')->notify(new JobFetched($msg));

    }

    public function connect_to_db()
    {

        // Create connection
        $conn = new \mysqli(env('REMOTE_DB_SERVER'), env('REMOTE_DB_USER'), env('REMOTE_DB_NAME'), env('REMOTE_DB_PASSWORD'));

        // Check connection
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        return $conn;
    }

    public function transformTime($apiTime)
    {
    // Parse the API time string into a Carbon instance
    $carbonTime = Carbon::parse($apiTime);

    // Format the Carbon instance into MySQL format
    $mysqlTime = $carbonTime->toDateTimeString(); // or toDateTimeString(), or toIso8601String()

    return $mysqlTime;
    }
}
