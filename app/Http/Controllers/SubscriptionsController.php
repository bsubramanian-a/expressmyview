<?php

namespace App\Http\Controllers;

use App\Channel;
use App\Subscription;
use App\Podcast;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SubscriptionsController extends Controller
{
    /**
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function changeSubscriptionStatus(Request $request, int $id)
    {
        $userId = Auth::user()->id;
        if ($request->segment(1) == 'channel') {
            $subscription = Subscription::where('channel_id', $id)->where('user_id', $userId)->first();
        } elseif ($request->segment(1) == 'video') {
            $video = Podcast::find($id)->first();
            $id = $video->channel_id;
            $subscription = Subscription::where('channel_id', $id)->where('user_id', $userId)->first();
        }

        $channel = Channel::find($id);
        if (empty($subscription)) {
            $newSubscription = new Subscription();
            $newSubscription->channel_id = $id;
            $newSubscription->user_id = $userId;

            DB::beginTransaction();
            if ($newSubscription->save()) {

                if ($channel->increment('subscribers')) {
                    DB::commit();

                    return new Response([
                        'status' => 1,
                        'action' => 'subscribed',
                        'subsCount' => $channel->subscribers
                    ]);
                } else {
                    DB::rollBack();

                    return new Response([
                        'status' => 0
                    ]);
                }

            } else {
                return new Response([
                    'status' => 0
                ]);
            }
        } else {
            DB::beginTransaction();
            if ($subscription->delete()) {

                if ($channel->decrement('subscribers')) {
                    DB::commit();

                    return new Response([
                        'status' => 1,
                        'action' => 'unsubscribed',
                        'subsCount' => $channel->subscribers
                    ]);
                } else {
                    DB::rollBack();

                    return new Response([
                        'status' => 0
                    ]);
                }
            } else {
                return new Response([
                    'status' => 0
                ]);
            }
        }
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function mySubscriptions()
    {
        $channels = Channel::whereIn(
            'id', Subscription::where('user_id', Auth::user()->id)->get(['channel_id'])->pluck('channel_id')->toArray()
        )->paginate(10);

        return view('users.my_subscriptions', compact('channels'));
    }

    public function subscribePodcast(Request $request)
    {
        $request = request();
        $pid = $request->pid;
        $userId = $request->uid;
        $podcast = Podcast::find($pid);
        $id = $podcast->channel_id;

        $subscription = Subscription::where('channel_id', $id)->where('user_id', $userId)->first();

        $channel = Channel::find($id);

        if (empty($subscription)) {
            $newSubscription = new Subscription();
            $newSubscription->channel_id = $id;
            $newSubscription->user_id = $userId;

            DB::beginTransaction();
            if ($newSubscription->save()) {

                if ($channel->increment('subscribers')) {
                    DB::commit();

                    return new Response([
                        'status' => 1,
                        'action' => 'subscribed',
                        'subsCount' => $channel->subscribers,
                        "msg" => "Successfully Subscribed"
                    ]);
                } else {
                    DB::rollBack();

                    return new Response([
                        'status' => 0,
                        'error' => "something went wrong, please try again later"
                    ]);
                }

            } else {
                return new Response([
                    'status' => 0,
                    'error' => "something went wrong, please try again later"
                ]);
            }
        } else {
            DB::beginTransaction();
            if ($subscription->delete()) {

                if ($channel->decrement('subscribers')) {
                    DB::commit();

                    return new Response([
                        'status' => 1,
                        'action' => 'unsubscribed',
                        'subsCount' => $channel->subscribers,
                        "msg" => "Successfully Unsubscribed"
                    ]);
                } else {
                    DB::rollBack();

                    return new Response([
                        'status' => 0,
                        'error' => "something went wrong, please try again later"
                    ]);
                }
            } else {
                return new Response([
                    'status' => 0,
                    'error' => "something went wrong, please try again later"
                ]);
            }
        }
    }

    public function subscribeChannel(Request $request)
    {
        $request = request();
        $id = $request->id;
        $userId = $request->uid;

        $subscription = Subscription::where('channel_id', $id)->where('user_id', $userId)->first();

        $channel = Channel::find($id);

        if (empty($subscription)) {
            $newSubscription = new Subscription();
            $newSubscription->channel_id = $id;
            $newSubscription->user_id = $userId;

            DB::beginTransaction();
            if ($newSubscription->save()) {

                if ($channel->increment('subscribers')) {
                    DB::commit();

                    return new Response([
                        'status' => 1,
                        'action' => 'subscribed',
                        'subsCount' => $channel->subscribers,
                        "msg" => "Successfully Subscribed"
                    ]);
                } else {
                    DB::rollBack();

                    return new Response([
                        'status' => 0,
                        'error' => "something went wrong, please try again later"
                    ]);
                }

            } else {
                return new Response([
                    'status' => 0,
                    'error' => "something went wrong, please try again later"
                ]);
            }
        } else {
            DB::beginTransaction();
            if ($subscription->delete()) {

                if ($channel->decrement('subscribers')) {
                    DB::commit();

                    return new Response([
                        'status' => 1,
                        'action' => 'unsubscribed',
                        'subsCount' => $channel->subscribers,
                        "msg" => "Successfully Unsubscribed"
                    ]);
                } else {
                    DB::rollBack();

                    return new Response([
                        'status' => 0,
                        'error' => "something went wrong, please try again later"
                    ]);
                }
            } else {
                return new Response([
                    'status' => 0,
                    'error' => "something went wrong, please try again later"
                ]);
            }
        }
    }

    public function mySubscriptionsAPI()
    {
        $request = request();
        $uid = $request->uid;
        $channels = Channel::whereIn(
            'id', Subscription::where('user_id', $uid)->get(['channel_id'])->pluck('channel_id')->toArray()
        )->get();

        if(!empty($channels)){
            foreach($channels as $channel){
                if($channel["logo"]){
                    $channel["logoPath"] = Storage::disk('s3')->url("public/users/logo/".$channel['logo']);
                }
            }
            return new Response([
                'status' => 0,
                'message' => $channels
            ]);
        }else{
            return new Response([
                'status' => 0,
                'error' => "you have not subscribed any channel"
            ]);
        }
        
        
    }
}
