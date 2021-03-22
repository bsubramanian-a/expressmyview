<?php

namespace App\Http\Controllers;

use App\Category;
use App\Channel;
use App\Podcast;
use Illuminate\Http\Request;
use App\LiveStream;
use Illuminate\Support\Facades\Storage;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
//        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
//        $categories = Category::where('status', 1)->withCount('videos')->get();
        $channels = Channel::where('status', 1)->orderBy('subscribers')->take(10)->get();

        $podcasts = Podcast::where('status', 1);
        $podcasts2 = clone $podcasts;

        $videoPodcasts = $podcasts->where('file_type', 'video');
        $videoPodcasts2 = clone $videoPodcasts;

        $audioPodcasts = $podcasts2->where('file_type', 'audio');
        $audioPodcasts2 = clone $audioPodcasts;

        $latestVideos = $videoPodcasts->latest()->take(4)->get();
        $popularVideos = $videoPodcasts2->orderBy('views')->take(4)->get();

        $latestAudios = $audioPodcasts->latest()->take(4)->get();
        $popularAudios = $audioPodcasts2->orderBy('views')->take(4)->get();

        $viewData = array(
//            'categories' => $categories,
            'channels' => $channels,
            'latestVideos' => $latestVideos,
            'popularVideos' => $popularVideos,
            'latestAudios' => $latestAudios,
            'popularAudios' => $popularAudios
        );
        return view('home', $viewData);
    }

    public function getChannelName($channelId){
        $channel = Channel::find($channelId);

        if($channel){
            return $channel->name;
        }else{
            return "";
        }
    }

    public function getHomeDatas(Request $request)
    {
        $id = $request->id;
        //        $categories = Category::where('status', 1)->withCount('videos')->get();
        $popularChannels = Channel::where('status', 1)->orderBy('subscribers', 'DESC')->take(10)->get();
        $latestVideoPodcasts = Podcast::where('privacy', 1)->where('file_type', "video")->orderBy('created_at', 'DESC')->take(4)->get();
        // $liveStreams =  LiveStream::where('status', 1)->whereNotIn('user_id', [$id])->orderBy('created_at', 'DESC')->take(4)->get();

        foreach ($popularChannels as $popularChannel ){
            if($popularChannel['logo']){
                $popularChannel["path"] = Storage::disk('s3')->url("public/users/logo/".$popularChannel['logo']);
            }
        }

        foreach ($latestVideoPodcasts as $latestVideoPodcast ){
            if($latestVideoPodcast['thumbnail']){
                $latestVideoPodcast["path"] = Storage::disk('s3')->url("public/podcast/thumbnail/".$latestVideoPodcast['thumbnail']);
            }
            $latestVideoPodcast["channelName"] = $this->getChannelName($latestVideoPodcast["channel_id"]);
            $latestVideoPodcast["dateDiff"] = $latestVideoPodcast['created_at']->diffForHumans();
        }

        // foreach ($liveStreams as $liveStream ){
        //     if($liveStream['thumbnail']){
        //         $liveStream["path"] = Storage::disk('s3')->url("public/podcast/thumbnail/".$liveStream['thumbnail']);
        //     }
        //     $liveStream["channelName"] = $this->getChannelName($liveStream["channel_id"]);
        //     $liveStream["dateDiff"] = $liveStream['created_at']->diffForHumans();
        // }

        if(empty($popularChannels) && empty($popularChannels) && empty($popularChannels)){
            return response()->json([
                'error' => "No data available right now, please try again later"
            ]);
        }else{
            $viewData = array(
                'popularChannels' => $popularChannels,
                'latestVideoPodcasts' => $latestVideoPodcasts,
                // 'liveStreams' => $liveStreams,
            );
        }

        return response()->json([
            'status' => 200,
            $viewData
        ]);
    }
}



