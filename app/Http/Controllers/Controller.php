<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{
    public function subscribe(Request $request)
    {
        $topic = $request->route('topic');
        $subscriber = $request->input('url');

        $subscribers = Cache::has($topic) ? explode(',', Cache::get($topic)) : [];
        if (!collect($subscribers)->contains($subscriber)) {
            $subscribers[] = $subscriber;
        }
        Cache::put($topic, implode(',', $subscribers));

        return response()->json("$subscriber is subscribed $topic");
    }

    public function publish(Request $request, Client $client)
    {
        $topic = $request->route('topic');
        $message = $request->input('message');

        $subscribers = Cache::has($topic) ? explode(',', Cache::get($topic)) : [];

        try {
            foreach ($subscribers as $subscriber) {
                $parseUrl = parse_url($subscriber);
                $isSelfHost = $parseUrl['host'] . ':' .  $parseUrl['port'] == $request->getHttpHost();
                $queryParameters = "?topic=$topic&message=$message";
                if (!$isSelfHost) {
                    $response = $client->request('GET', $subscriber . $queryParameters, [
                        'header' => [
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/json'
                        ]
                    ]);
                } else {
                    $selfRequest = Request::create($parseUrl['path'] . $queryParameters, 'GET');
                    $response = app()->handle($selfRequest);
                }

            }
        } catch (GuzzleException $e) {
            Log::error($e->getMessage());
        }

        return response()->json("message is sent");
    }

    public function event(Request $request)
    {
        $topic = $request->input('topic');
        $message = $request->input('message');
        $response = "Topic $topic - '$message' is received";
        Log::info($response);
        return response()->json($response);
    }
}
