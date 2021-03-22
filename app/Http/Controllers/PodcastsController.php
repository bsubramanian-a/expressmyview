<?php

namespace App\Http\Controllers;

use App\Like;
use App\Podcast;
use App\Cast;
use App\Category;
use App\Comment;
use App\History;
use App\Jobs\ProcessAudioPodcast;
use App\Jobs\ProcessPodcast;
use App\Language;
use App\License;
use App\Tag;
use App\Subscription;
use App\User;
use Hashids\Hashids;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Filesystem\Filesystem;
use FFMpeg;
use FFMpeg\Coordinate\Dimension;
use FFMpeg\Format\Video\X264;
use App\Channel;
use Carbon;
class PodcastsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param User|null $user
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index(Request $request)
    {
        $type = $request->route('type');
        $user = $request->route('user');
        $orderBy = $request->get('orderBy');

        $podcasts = Podcast::where('status', 1)
            ->when($type, function ($query, $type) {
                return $query->where('file_type', $type);
            })
            ->when($user, function ($query, $user) {
                return $query->where('user_id', $user);
            })
            ->when($orderBy, function ($query, $orderBy) {
                if ($orderBy == 'popularity') {
                    return $query->orderBy('views', 'desc');
                } elseif ($orderBy == 'rating') {
                    return $query->orderBy('likes', 'desc');
                } else {
                    return $query->orderBy('created_at', 'desc');
                }
            });

        if (empty($type)) {
            $podcasts2 = clone $podcasts;

            $videoPodcasts = $podcasts->where('file_type', 'video')->paginate(1, ['*'], 'video-page');
            $audioPodcasts = $podcasts2->where('file_type', 'audio')->paginate(1, ['*'], 'audio-page');
        } else {
            ${$type . 'Podcasts'} = $podcasts->paginate(10);
        }

        $viewData = array(
            'videos' => !empty($videoPodcasts) ? $videoPodcasts : null,
            'audios' => !empty($audioPodcasts) ? $audioPodcasts : null,
            'type' => $type
        );
        return view('users.videos.index', $viewData);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function create(Request $request)
    {
        if (Auth::user()->channels()->count() == 0) {
            return redirect()->route('channel.create');
        }

        $viewData = [
            'channels' => Auth::user()->channels,
            'categories' => Category::where('status', 1)->get(),
            'licenses' => License::where('status', 1)->get(),
            'languages' => Language::all(),
            'selectedChannel' => $request->input('channel')
        ];
        return view('users.videos.upload', $viewData);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'video' => 'required|file|mimetypes:video/*,audio/*'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->getMessageBag()->first()
            ]);
        } else {
            $file = $request->file('video');

            // Check whether uploaded file is video or audio file
            $mimeType = $file->getMimeType();
            $fileType = explode('/', $mimeType)[0];

            // Upload file
            $originalFilenameWithExtension = $file->getClientOriginalName();
            $originalFilename = substr($originalFilenameWithExtension, 0, strrpos($originalFilenameWithExtension, '.'));
            $hashids = new Hashids(microtime(), 10);
            $filenameWithoutExtension = $hashids->encode(rand(1, 100) . time());
            $filename = $filenameWithoutExtension . '.' . ($fileType == 'video' ? $file->extension() : 'mp3');
            $size = $file->getSize();
            $file->storeAs('podcast', $filename, 'public');

            $media = FFMpeg::fromDisk('public')->open('podcast/' . $filename);
            $duration = $media->getDurationInSeconds();

            if ($fileType == 'video') {
                // Get video duration and dimensions
                $dimensions = $media->getVideoStream()->getDimensions();

                /*$ffVideo = $ffprobe
                    ->streams(storage_path('/app/public/podcast/' . $filename))
                    ->videos()
                    ->first();
                $duration = $ffVideo->get('duration');
                $dimensions = $ffVideo->getDimensions();*/

                // Create video thumbnail
                $thumbnailFilename = $filenameWithoutExtension . '.jpg';
                $media
                    ->getFrameFromSeconds((int)($duration * 0.3))
                    ->export()
                    ->toDisk('public')
                    ->save('podcast/thumbnail/' . $thumbnailFilename);

                /*$ffmpeg = FFMpeg::create();
                $thumbnailTime = (int)($duration * 0.3);
                $ffmpegVideo = $ffmpeg->open(storage_path('/app/public/podcast/' . $filename));
                $thumbnailPath = storage_path('/app/public/podcast/thumbnail');
                if (!file_exists($thumbnailPath)) {
                    mkdir($thumbnailPath, 0777, true);
                }
                $thumbnailFilename = $filenameWithoutExtension . '.jpg';
                $thumbnail = $thumbnailPath . '/' . $thumbnailFilename;
                $ffmpegVideo
                    ->frame(\FFMpeg\Coordinate\TimeCode::fromSeconds($thumbnailTime))
                    ->save($thumbnail);*/
            } else {
                $thumbnailFilename = '';
            }

            // Save podcast data to database table (videos)
            $podcast = new Podcast();
            $podcast->filename = $filename;
            $podcast->file_type = $fileType == 'video' ? 'video' : 'audio';
            $podcast->size = $size;
            $podcast->user_id = Auth::user()->id;
            $podcast->runtime = $duration;
            $podcast->runtime_formatted = formatVideoRuntime($duration);
            $podcast->thumbnail = $thumbnailFilename;
            if ($podcast->save()) {
                if ($fileType == 'video') {
                    ProcessPodcast::dispatch($podcast, $dimensions->getWidth(), $dimensions->getHeight());
                } else {
                    ProcessAudioPodcast::dispatch($podcast);
                }

                return response()->json([
                    'title' => $originalFilename,
                    'thumbnail' => !empty($thumbnailFilename) ? url('/storage/podcast/thumbnail/' . $thumbnailFilename) : $thumbnailFilename,
                    'file_type' => $fileType == 'video' ? 'video' : 'audio',
                    'action' => route('podcast.update', $podcast->id)
                ], 201);
            } else {
                return response()->json([
                    'error' => 'An error occurred. Please try again.'
                ]);
            }

        }

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param Podcast $podcast
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function show(Podcast $podcast)
    {
        if (Auth::check()) {
            $userId = Auth::user()->id;
            if ($userId !== $podcast->user->id) {
                $podcast->views += 1;
                $podcast->save();
            }

            $history = History::where('user_id', $userId)->where('podcast_id', $podcast->id)->first();
            if (empty($history)) {
                $history = new History();
                $history->user_id = $userId;
                $history->podcast_id = $podcast->id;
                $history->save();
            }
        } else {
            $podcast->views += 1;
            $podcast->save();
        }

        $casts = $podcast->casts;
        $categories = $podcast->categories;
        $tags = $podcast->tags;

        $commentsCount = Comment::where('podcast_id', $podcast->id)->count();
        $comments = Comment::where('podcast_id', $podcast->id)->with('user.profile', 'likes')->latest()->paginate(3);

        $podcastCategories = $podcast->categories()->pluck('categories.id')->toArray();

        // Get related podcasts
        $relatedPodcastsCount = 0;

        $relatedPodcastsObj = Podcast::where('id', '!=', $podcast->id)->where('status', 1);
        $relatedPodcastsObj2 = clone $relatedPodcastsObj;

        $relatedPodcasts1 = $relatedPodcastsObj
            ->where('user_id', $podcast->user_id)
            ->inRandomOrder()
            ->take(3)
            ->get();

        $othersPodcastsObj = $relatedPodcastsObj2->where('user_id', '!=', $podcast->user_id);
        $othersPodcastsObj2 = clone $othersPodcastsObj;

        $relatedPodcasts2 = $othersPodcastsObj
            ->whereHas('categories', function ($query) use ($podcastCategories) {
                $query->whereIn('categories.id', $podcastCategories);
            })
            ->inRandomOrder()
            ->take(3)
            ->get();
        $relatedPodcastsCount += $relatedPodcasts1->count() + $relatedPodcasts2->count();

        $relatedPodcasts3 = $othersPodcastsObj2
            ->inRandomOrder()
            ->take(9 - $relatedPodcastsCount)
            ->get();

        $relatedPodcasts = $relatedPodcasts1->merge($relatedPodcasts2);
        $relatedPodcasts = $relatedPodcasts->merge($relatedPodcasts3);

        $viewData = array(
            'podcast' => $podcast,
            'casts' => $casts,
            'categories' => $categories,
            'tags' => $tags,
            'comments' => $comments,
            'commentsCount' => $commentsCount,
            'moreComments' => $commentsCount > 3,
            'relatedPodcasts' => $relatedPodcasts
        );
        return view('users.videos.single', $viewData);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param Podcast $podcast
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function edit(Podcast $podcast)
    {
        $viewData = array(
            'video' => $podcast,
            'channels' => Auth::user()->channels,
            'categories' => Category::where('status', 1)->get(),
            'licenses' => License::where('status', 1)->get(),
            'languages' => Language::all(),
            'tags' => $podcast->tags()->get(['name'])->pluck('name')->toArray(),
            'casts' => $podcast->casts()->get(['name'])->pluck('name')->toArray(),
            'selectedCategories' => $podcast->categories()->get(['category_id'])->pluck('category_id')->toArray()
        );
        return view('users.videos.edit', $viewData);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param Podcast $podcast
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Podcast $podcast)
    {
        $validator = Validator::make($request->all(), [
            'channel' => 'required',
            'title' => 'required|string',
            'privacy' => 'required',
            'license' => 'required',
            'languages' => 'required',
            'comments_allowed' => 'required',
            'categories' => 'required',
            'thumbnail' => 'nullable|image'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'error' => $validator->getMessageBag()->toArray()
            ]);
        } else {
            if ($request->hasFile('thumbnail')) {
                $coverFilename = null;
                $oldCoverFilename = $podcast->thumbnail;
                if ($request->file('thumbnail')->store('public/podcast/thumbnail')) {
                    $coverFilename = $request->file('thumbnail')->hashName();
                    if (!empty($oldCoverFilename)) {

                        unlink(storage_path('/app/public/podcast/thumbnail') . '/' . $oldCoverFilename);
                    }

                } else {
                    return response()->json([
                        'errors' => 'An error occurred. Please try again.'
                    ]);
                }
            } else {
                $thumbfile = storage_path('/app/public/podcast/thumbnail') . '/' . $podcast->thumbnail;

                Storage::disk('s3')->put('public/podcast/thumbnail/' . $podcast->thumbnail, file_get_contents($thumbfile), 'public');

                $coverFilename = $podcast->thumbnail;
            }

            $podcast->channel_id = $request->input('channel');
            $podcast->title = $request->input('title');
            $podcast->description = $request->input('description');
            $podcast->privacy = $request->input('privacy');
            $podcast->license_id = $request->input('license');
            $podcast->comments_allowed = $request->input('comments_allowed');
            $podcast->thumbnail = $coverFilename;
            $podcast->status = 1;
            DB::beginTransaction();
            try {
                $podcast->save();

                $languages = $request->input('languages');
                $podcast->languages()->sync($languages);

                $categories = $request->input('categories');
                $podcast->categories()->sync($categories);

                $tags = explode(',', $request->input('tags'));
                $tagIds = array();
                foreach ($tags as $tag) {
                    if (!empty($tag)) {
                        $tagDetails = Tag::where('name', $tag)->first();
                        if (!empty($tagDetails)) {
                            $tagIds[] = $tagDetails->id;
                        } else {
                            $newTag = new Tag();
                            $newTag->name = $tag;
                            $newTag->slug = Str::slug($tag);
                            $newTag->save();
                            $tagIds[] = $newTag->id;
                        }
                    }
                }
                $podcast->tags()->sync($tagIds);

                $casts = explode(',', $request->input('cast'));
                $castIds = array();
                foreach ($casts as $cast) {
                    if (!empty($cast)) {
                        $castDetails = Cast::where('name', $cast)->first();
                        if (!empty($castDetails)) {
                            $castIds[] = $castDetails->id;
                        } else {
                            $newCast = new Cast();
                            $newCast->name = $cast;
                            $newCast->slug = Str::slug($cast);
                            $newCast->save();
                            $castIds[] = $newCast->id;
                        }
                    }
                }
                $podcast->casts()->sync($castIds);

                DB::commit();

                return response()->json([
                    'status' => 1,
                    'message' => 'Podcast saved successfully.',
                    'redirect' => route('podcast.show', $podcast->id)
                ]);
            } catch (\Exception $exception) {
                DB::rollBack();

                return response()->json([
                    'status' => 0,
                    'message' => 'An error occurred. Please try again.',
                ]);
            }
        }
    }

    public function relatedPodcasts(Request $request)
    {
        $pid = $request->pid;   
        $podcast = Podcast::find($pid);
        
        $podcastCategories = $podcast->categories()->pluck('categories.id')->toArray();

        $relatedPodcastsObj = Podcast::where('id', '!=', $pid)->where('status', 1);

        if(empty($podcastCategories)){
            $relatedPodcasts2 = $relatedPodcastsObj->inRandomOrder()->take(5)->get();
        }else{

            $relatedPodcasts2 = $relatedPodcastsObj
                ->whereHas('categories', function ($query) use ($podcastCategories) {
                    $query->whereIn('categories.id', $podcastCategories);
                })
                ->inRandomOrder()
                ->take(5)
                ->get();
        }
       
            
        return response()->json([
            'status' => 0,
            'relatedPodcast' => $relatedPodcasts2,
            'message' => "success"
        ]);
        
    }

    public function uploadPodcastDetails(Request $request)
    {
        $request = request();
        $podcastId = $request->input('podcast_id');
        $podcast = Podcast::find($podcastId);

        $validator = Validator::make($request->all(), [
            'channel' => 'required',
            'title' => 'required|string',
            'privacy' => 'required',
            'license' => 'required',
            'languages' => 'required',
            'comments_allowed' => 'required',
            'categories' => 'required',
            'thumbnail' => 'nullable|image'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'error' => $validator->getMessageBag()->toArray()
            ]);
        } else {
            if ($request->hasFile('thumbnail')) {
                $coverFilename = null;
                $oldCoverFilename = $podcast->thumbnail;
                if ($request->file('thumbnail')->store('public/podcast/thumbnail')) {
                    $coverFilename = $request->file('thumbnail')->hashName();
                    if (!empty($oldCoverFilename)) {

                        unlink(storage_path('/app/public/podcast/thumbnail') . '/' . $oldCoverFilename);
                    }

                } else {
                    return response()->json([
                        'errors' => 'An error occurred. Please try again.'
                    ]);
                }
            } else {
                if($podcast->thumbnail){
                    $thumbfile = storage_path('/app/public/podcast/thumbnail') . '/' . $podcast->thumbnail;

                    Storage::disk('s3')->put('public/podcast/thumbnail/' . $podcast->thumbnail, file_get_contents($thumbfile), 'public');
    
                    $coverFilename = $podcast->thumbnail;
                }
            }

            $podcast->channel_id = $request->input('channel');
            $podcast->title = $request->input('title');
            $podcast->description = $request->input('description');
            $podcast->privacy = $request->input('privacy');
            $podcast->license_id = $request->input('license');
            $podcast->comments_allowed = $request->input('comments_allowed');
            $podcast->thumbnail = $coverFilename;
            $podcast->status = 1;
            DB::beginTransaction();
            try {
                $podcast->save();

                $languages = $request->input('languages');
                // $podcast->languages()->sync($languages);
                $langIds = array();
                foreach ($languages as $lng) {
                    if (!empty($lng)) {
                        $langDetails = Language::where('name', $lng)->first();
                        if (!empty($langDetails)) {
                            $langIds[] = $langDetails->id;
                        }
                    }
                }
                foreach ($langIds as $lngid) {
                    $allLangs = DB::table('language_podcast')->where([
                            'language_id' => $lngid,
                            'podcast_id' => $podcastId
                        ])->first();

                    if(!$allLangs){
                        DB::table('language_podcast')->insert([
                            ['language_id' => $lngid, 'podcast_id' => $podcastId]
                        ]);
                    }
                }

                $categories = $request->input('categories');
                $catIds = array();
                foreach ($categories as $category) {
                    if (!empty($category)) {
                        $catDetails = Category::where('name', $category)->first();
                        if (!empty($catDetails)) {
                            $catIds[] = $catDetails->id;
                        }
                    }
                }
                
                foreach ($catIds as $catid) {
                    $allCats = DB::table('category_podcast')->where([
                            'category_id' => $catid,
                            'podcast_id' => $podcastId
                        ])->first();

                    if(!$allCats){
                        DB::table('category_podcast')->insert([
                            ['category_id' => $catid, 'podcast_id' => $podcastId]
                        ]);
                    }
                }

                // $podcast->categories()->sync($categories);

                $tags = $request->input('tags');
                $tagIds = array();
                foreach ($tags as $tag) {
                    if (!empty($tag)) {
                        $tagDetails = Tag::where('name', $tag)->first();
                        if (!empty($tagDetails)) {
                            $tagIds[] = $tagDetails->id;
                        } else {
                            $newTag = new Tag();
                            $newTag->name = $tag;
                            $newTag->slug = Str::slug($tag);
                            $newTag->save();
                            $tagIds[] = $newTag->id;
                        }
                    }
                }

                foreach ($tagIds as $tagid) {
                    $allTags = DB::table('podcast_tag')->where([
                            'tag_id' => $tagid,
                            'podcast_id' => $podcastId
                        ])->first();
                    
                    if(!$allTags){
                        DB::table('podcast_tag')->insert([
                            ['tag_id' => $tagid, 'podcast_id' => $podcastId]
                        ]);
                    }
                }
                // $podcast->tags()->sync($tagIds);

                $casts = $request->input('casts');
                $castIds = array();
                foreach ($casts as $cast) {
                    if (!empty($cast)) {
                        $castDetails = Cast::where('name', $cast)->first();
                        if (!empty($castDetails)) {
                            $castIds[] = $castDetails->id;
                        } else {
                            $newCast = new Cast();
                            $newCast->name = $cast;
                            $newCast->slug = Str::slug($cast);
                            $newCast->save();
                            $castIds[] = $newCast->id;
                        }
                    }
                }

                foreach ($castIds as $castid) {
                    $allCasts = DB::table('cast_podcast')->where([
                            'cast_id' => $castid,
                            'podcast_id' => $podcastId
                        ])->first();
                    
                    if(!$allCasts){
                        DB::table('cast_podcast')->insert([
                            ['cast_id' => $castid, 'podcast_id' => $podcastId]
                        ]);
                    }
                }
                // $podcast->casts()->sync($castIds);

                DB::commit();

                return response()->json([
                    'status' => 1,
                    'message' => 'Podcast saved successfully.',
                ]);
            } catch (\Exception $exception) {
                DB::rollBack();

                return response()->json([
                    'status' => 0,
                    'message' => 'An error occurred. Please try again.',
                ]);
            }
        }
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param Podcast $podcast
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function destroy(Podcast $podcast)
    {
        if ($podcast->user_id == Auth::user()->id) {
            DB::beginTransaction();;
            if ($podcast->delete()) {

//                unlink(storage_path('/app/public/podcast/' . $podcast->filename));
                Storage::disk('s3')->delete('public/podcast/thumbnail/' . $podcast->thumbnail);
                if ($podcast->file_type == 'video') {
                    try {
                        if (Storage::disk('s3')->exists('public/podcast/360/' . $podcast->filename)) {
                            Storage::disk('s3')->delete('public/podcast/360/' . $podcast->filename);
                        }
                        if (Storage::disk('s3')->exists('public/podcast/480/' . $podcast->filename)) {
                            Storage::disk('s3')->delete('public/podcast/480/' . $podcast->filename);
                        }
                        if (Storage::disk('s3')->exists('public/podcast/720/' . $podcast->filename)) {
                            Storage::disk('s3')->delete('public/podcast/720/' . $podcast->filename);
                        }
                        if (Storage::disk('s3')->exists('public/podcast/1080/' . $podcast->filename)) {
                            Storage::disk('s3')->delete('public/podcast/1080/' . $podcast->filename);
                        }

                    } catch (\Exception $exception) {
                        //
                    }
                } else {
                    if (Storage::disk('s3')->exists('public/podcast/audio/' . $podcast->filename)) {
                        Storage::disk('s3')->delete('public/podcast/audio/' . $podcast->filename);
                    }
                }

                return new Response([
                    'status' => 1,
                    'message' => 'Podcast deleted.',
                ]);
            } else {
                return new Response([
                    'status' => 0,
                    'message' => 'An error occurred. Please try again.',
                ]);
            }
        } else {
            return new Response([
                'status' => 0,
                'message' => 'You do not have permission to do that.',
            ]);
        }
    }

    public function getPodcastsByCategory(Category $category)
    {
        $podcasts = Podcast::select('podcasts.*')
            ->where('podcasts.status', 1)
            ->leftJoin('category_podcast', 'podcasts.id', '=', 'category_podcast.podcast_id')
            ->leftJoin('categories', 'category_podcast.category_id', '=', 'categories.id')
            ->where('categories.id', $category->id);
        $podcasts2 = clone $podcasts;
        $videoPodcasts = $podcasts
            ->where('file_type', 'video')
            ->latest()
            ->paginate(10);
        $audioPodcasts = $podcasts2
            ->where('file_type', 'audio')
            ->latest()
            ->paginate(10);

        dump($videoPodcasts);
        dd($audioPodcasts);
    }

    public function getPodcastsByTag(Tag $tag)
    {
        $podcasts = Podcast::select('podcasts.*')
            ->where('podcasts.status', 1)
            ->leftJoin('podcast_tag', 'podcasts.id', '=', 'podcast_tag.podcast_id')
            ->leftJoin('tags', 'podcast_tag.tag_id', '=', 'tags.id')
            ->where('tags.id', $tag->id);
        $podcasts2 = clone $podcasts;
        $videoPodcasts = $podcasts
            ->where('file_type', 'video')
            ->latest()
            ->paginate(10);
        $audioPodcasts = $podcasts2
            ->where('file_type', 'audio')
            ->latest()
            ->paginate(10);

        dump($videoPodcasts);
        dd($audioPodcasts);
    }

    public function getMyPodcasts(Request $request)
    {
        $type = $request->route('type');
        $user = Auth::user()->id;
        $orderBy = $request->get('orderBy');

        $podcasts = Podcast::where('status', 1)->where('user_id', $user)
            ->when($orderBy, function ($query, $orderBy) {
                if (empty($orderBy) || $orderBy == 'date') {
                    return $query->orderBy('created_at', 'desc');
                } elseif ($orderBy == 'popularity') {
                    return $query->orderBy('views', 'desc');
                } else {
                    return $query;
                }
            });

        if (empty($type)) {
            $podcasts2 = clone $podcasts;

            $videoPodcasts = $podcasts->where('file_type', 'video')->paginate(10, ['*'], 'video-page');
            $audioPodcasts = $podcasts2->where('file_type', 'audio')->paginate(10, ['*'], 'audio-page');
        } else {
            ${$type . 'Podcasts'} = $podcasts->where('file_type', $type)->paginate(10);
        }

        $viewData = array(
            'videos' => !empty($videoPodcasts) ? $videoPodcasts : null,
            'audios' => !empty($audioPodcasts) ? $audioPodcasts : null,
            'type' => $type
        );
        return view('users.videos.index', $viewData);
    }

    /**
     * @param Podcast $podcast
     * @return Response
     */
    public function changeLikeStatus(Podcast $podcast)
    {
        $user = Auth::user();
        $liked = Like::where('podcast_id', $podcast->id)->where('user_id', $user->id)->first();

        if (empty($liked)) {
            DB::beginTransaction();

            $like = new Like();
            $like->podcast_id = $podcast->id;
            $like->user_id = $user->id;
            if ($like->save()) {

                if ($podcast->increment('likes')) {
                    DB::commit();

                    return new Response([
                        'status' => 1,
                        'action' => 'liked',
                        'likesCount' => $podcast->likes
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

            if ($liked->delete()) {

                if ($podcast->decrement('likes')) {
                    DB::commit();

                    return new Response([
                        'status' => 1,
                        'action' => 'unliked',
                        'likesCount' => $podcast->likes
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

    public function uploadMedia(Request $request){
        // $request = request();
        $validator = Validator::make($request->all(), [
            'video' => 'required|file|mimetypes:video/*,audio/*'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->getMessageBag()->first()
            ]);
        } else {
            $file = $request->file('video');

            // Check whether uploaded file is video or audio file
            $mimeType = $file->getMimeType();
            $fileType = explode('/', $mimeType)[0];

            // Upload file
            $originalFilenameWithExtension = $file->getClientOriginalName();
            $originalFilename = substr($originalFilenameWithExtension, 0, strrpos($originalFilenameWithExtension, '.'));
            $hashids = new Hashids(microtime(), 10);
            $filenameWithoutExtension = $hashids->encode(rand(1, 100) . time());
            $filename = $filenameWithoutExtension . '.' . ($fileType == 'video' ? $file->extension() : 'mp3');
            $size = $file->getSize();
            $file->storeAs('podcast', $filename, 'public');
            // $filesystem = Storage::get("podcast/". $filename);
            // $media = FFMpeg::fromFilesystem($filesystem)->open('podcast/' . $filename);
            $media = FFMpeg::fromDisk('public')->open('podcast/' . $filename);
            $duration = $media->getDurationInSeconds();

            if ($fileType == 'video') {
                // Get video duration and dimensions
                $dimensions = $media->getVideoStream()->getDimensions();

                // Create video thumbnail
                $thumbnailFilename = $filenameWithoutExtension . '.jpg';
                $media
                    ->getFrameFromSeconds((int)($duration * 0.3))
                    ->export()
                    ->toDisk('public')
                    ->save('podcast/thumbnail/' . $thumbnailFilename);

            } else {
                $thumbnailFilename = '';
            }

            // Save podcast data to database table (videos)
            $podcast = new Podcast();
            $podcast->filename = $filename;
            $podcast->file_type = $fileType == 'video' ? 'video' : 'audio';
            $podcast->size = $size;
            $podcast->user_id = Auth::user()->id;
            $podcast->runtime = $duration;
            $podcast->runtime_formatted = formatVideoRuntime($duration);
            $podcast->thumbnail = $thumbnailFilename;
            if ($podcast->save()) {
                if ($fileType == 'video') {
                    ProcessPodcast::dispatch($podcast, $dimensions->getWidth(), $dimensions->getHeight());
                } else {
                    ProcessAudioPodcast::dispatch($podcast);
                }

                return response()->json([
                    'title' => $originalFilename,
                    'thumbnail' => !empty($thumbnailFilename) ? url('/storage/podcast/thumbnail/' . $thumbnailFilename) : $thumbnailFilename,
                    'file_type' => $fileType == 'video' ? 'video' : 'audio',
                    'action' => route('podcast.update', $podcast->id)
                ], 201);
            } else {
                return response()->json([
                    'error' => 'An error occurred. Please try again.'
                ]);
            }

        }


        // $file = Request::file('mediafile');
        // $filename = Request::post('fileName');
        // $path = public_path().'/uploads/';
        
        // $mimeType = $file->getMimeType();
        // $fileType = explode('/', $mimeType)[0];
        // $userid = Request::post('userid');
        // $filetype = Request::post('filetype');
        // $runtime = Request::post('runtime');
        // $size = Request::post('size');
        // // Upload file
        // $current = time();
        // $originalFilenameWithExtension = $file->getClientOriginalName();
        // $originalFilename = substr($originalFilenameWithExtension, 0, strrpos($originalFilenameWithExtension, '.'));
        // $hashids = new Hashids(microtime(), 10);
        // $filenameWithoutExtension = $hashids->encode(rand(1, 100) . time());
        // $filename = $filenameWithoutExtension . '_' . $userid . '_' . $current . '.' . ($fileType == 'video' ? $file->extension() : 'mp3');
        // $file->move($path,$filename);
        // $newPodcast = new Podcast;
        // $newPodcast->user_id = isset($userid)?$userid:"";
        // $newPodcast->filename = isset($filename)?$filename:"";
        // $newPodcast->file_type = isset($filetype)?$filetype:"";
        // $newPodcast->channel_id = NULL;
        // $newPodcast->title = "";
        // $newPodcast->description = "";
        // $newPodcast->size = isset($size)?$size: 0;
        // $newPodcast->runtime = isset($runtime)?$runtime: NULL;
        // $newPodcast->runtime_formatted = "";
        // $newPodcast->privacy = 1;
        // $newPodcast->license_id = 0;
        // $newPodcast->monetize = 0;
        // $newPodcast->comments_allowed = 1;
        // $newPodcast->thumbnail = "";
        // $newPodcast->converted = 0;
        // $newPodcast->status = 2;
        // $newPodcast->views = 0;
        // $newPodcast->likes =0;
        // $newPodcast->dislikes = 0;
        // $newPodcast->comments_count = 0;
        // $newPodcast->save();
        // return $userid;
    }

    protected function updatePodcast(Request $request)
    {
        $rules = [
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'username' => 'required|string|max:255',
            'phonenumber' => 'required|string|max:255',
            'address1' => 'required|string|max:255',
            'address2' => 'string|max:255',
            'state' => 'required|string|max:255',
            'zip' => 'required|string|max:255',
        ];

        $id =  $data['id'];
        
        $data = $request->all();
         
        $input     = $request->only('firstname', 'lastname', 'username', 'phonenumber', 'address1', 'address2', 'state', 'zip');
        $validator = Validator::make($input, $rules);
        
        $user = User::find($id);

        if ($validator->fails()) {
            return redirect()->back()->with("error","Something went wrong.")->withInput();
        }

        if ($user->count() == 0) {
            $user = array(
                "error" => "User not found"
            );
            return redirect()->back()->with("error","Something went wrong.")->withInput();
        }
        
        $user->firstname = $data['firstname'];
        $user->lastname = $data['lastname'];
        $user->phonenumber = $data['phonenumber'];
        $user->address1 = $data['address1'];
        $user->address2 = $data['address2'];
        $user->state = $data['state'];
        $user->zip = $data['zip'];
        $user->save();

        return redirect()->back()->with("success","User Updated Successfully.")->withInput();
    }
 
    public function updatePodcastDetails(Request $request)
    {
        $request = request();
        $podcastId = $request->input('id');
        $podcast = Podcast::find($podcastId);
       
        $validator = Validator::make($request->all(), [
            'channel' => 'required',
            'title' => 'required',
            'privacy' => 'required',
            'license' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 0,
                'error' => $validator->getMessageBag()->toArray()
            ]);
        } else {

            $oldCoverFilename = $podcast->thumbnail;
            if ($request->input('thumbnail')) {
                $img = $request->input('thumbnail');
                if (preg_match('/^data:image\/(\w+);base64,/', $img)) {
                    $coverFilename = null;
                    $data = substr($img, strpos($img, ',') + 1);
                    $data = base64_decode($data);
                    $type = explode('/', mime_content_type($img))[1];
                    $currentDateTime = Carbon\Carbon::now();
                    $currentDateTimeString = $currentDateTime->toDateTimeString();
                    $coverFilename = $currentDateTime."_".".".$type;
                    Storage::disk('s3')->delete('public/podcast/thumbnail/'. $oldCoverFilename);
                    $p = Storage::disk('s3')->put('public/podcast/thumbnail/' . $coverFilename, $data, 'public');
                }else{
                    $coverFilename = $oldCoverFilename;
                }
            }else{
                $coverFilename = $oldCoverFilename;
            }
            
            $podcast->channel_id = $request->input('channel');
            $podcast->title = $request->input('title');
            $podcast->description = $request->input('description');
            $podcast->privacy = $request->input('privacy');
            $podcast->license_id = $request->input('license');
            $podcast->thumbnail = $coverFilename;
            $podcast->status = 1;
            DB::beginTransaction();
            try {
                $podcast->save();

                $languages = $request->input('languages');
                if($languages){
                    $langIds = array();
                    foreach ($languages as $lng) {
                        if (!empty($lng)) {
                            $langDetails = Language::where('name', $lng)->first();
                            if (!empty($langDetails)) {
                                $langIds[] = $langDetails->id;
                            }
                        }
                    }
                
                    foreach ($langIds as $lngid) {
                        $allLangs = DB::table('language_podcast')->where([
                                'language_id' => $lngid,
                                'podcast_id' => $podcastId
                            ])->first();

                        
                        if(!$allLangs){
                            DB::table('language_podcast')->insert([
                                ['language_id' => $lngid, 'podcast_id' => $podcastId]
                            ]);
                        }
                    }
                }
                

                $categories = $request->input('categories');
                if($categories){
                    $catIds = array();
                    foreach ($categories as $category) {
                        if (!empty($category)) {
                            $catDetails = Category::where('name', $category)->first();
                            if (!empty($catDetails)) {
                                $catIds[] = $catDetails->id;
                            }
                        }
                    }
                    
                    foreach ($catIds as $catid) {
                        $allCats = DB::table('category_podcast')->where([
                                'category_id' => $catid,
                                'podcast_id' => $podcastId
                            ])->first();

                        if(!$allCats){
                            DB::table('category_podcast')->insert([
                                ['category_id' => $catid, 'podcast_id' => $podcastId]
                            ]);
                        }
                    }
                }

                $tags = $request->input('tags');
                if($tags){
                    $tagIds = array();
                    foreach ($tags as $tag) {
                        if (!empty($tag)) {
                            $tagDetails = Tag::where('name', $tag)->first();
                            if (!empty($tagDetails)) {
                                $tagIds[] = $tagDetails->id;
                            } else {
                                $newTag = new Tag();
                                $newTag->name = $tag;
                                $newTag->slug = Str::slug($tag);
                                $newTag->save();
                                $tagIds[] = $newTag->id;
                            }
                        }
                    }

                    foreach ($tagIds as $tagid) {
                        $allTags = DB::table('podcast_tag')->where([
                                'tag_id' => $tagid,
                                'podcast_id' => $podcastId
                            ])->first();
                        
                        if(!$allTags){
                            DB::table('podcast_tag')->insert([
                                ['tag_id' => $tagid, 'podcast_id' => $podcastId]
                            ]);
                        }
                    }
                }
            
                // // $podcast->tags()->sync($tagIds);

                $casts = $request->input('casts');
                if($casts){
                    $castIds = array();
                    foreach ($casts as $cast) {
                        if (!empty($cast)) {
                            $castDetails = Cast::where('name', $cast)->first();
                            if (!empty($castDetails)) {
                                $castIds[] = $castDetails->id;
                            } else {
                                $newCast = new Cast();
                                $newCast->name = $cast;
                                $newCast->slug = Str::slug($cast);
                                $newCast->save();
                                $castIds[] = $newCast->id;
                            }
                        }
                    }

                    foreach ($castIds as $castid) {
                        $allCasts = DB::table('cast_podcast')->where([
                                'cast_id' => $castid,
                                'podcast_id' => $podcastId
                            ])->first();
                        
                        if(!$allCasts){
                            DB::table('cast_podcast')->insert([
                                ['cast_id' => $castid, 'podcast_id' => $podcastId]
                            ]);
                        }
                    }
                }

                DB::commit();

                return response()->json([
                    'status' => 1,
                    'message' => 'Podcast saved successfully.',
                ]);
            } catch (\Exception $exception) {
                DB::rollBack();

                return response()->json([
                    'status' => 0,
                    'message' => 'An error occurred. Please try again.',
                ]);
            }
        }
    }

    public function getChannelName($channelId){
        $channel = Channel::find($channelId);

        if($channel){
            return $channel->name;
        }else{
            return "";
        }
    }

    public function getAllVideoPodcasts()
    {
        $allVideoPodcasts = Podcast::where('privacy', 1)->where('file_type', "video")->orderBy('created_at', 'DESC')->get();

        foreach ($allVideoPodcasts as $allVideoPodcast ){
            if($allVideoPodcast['thumbnail']){
                $allVideoPodcast["path"] = Storage::disk('s3')->url("public/podcast/thumbnail/".$allVideoPodcast['thumbnail']);
            }
            $allVideoPodcast["channelName"] = $this->getChannelName($allVideoPodcast["channel_id"]);
            $allVideoPodcast["dateDiff"] = $allVideoPodcast['created_at']->diffForHumans();
        }

        if(empty($allVideoPodcast)){
            return response()->json([
                'error' => "No data available right now, please try again later"
            ]);
        }else{
            $viewData = array(
                'allVideoPodcasts' => $allVideoPodcasts,
            );
        }

        return response()->json([
            'status' => 200,
            $viewData
        ]);
    }

    public function getPodcastDetails(Request $request)
    {   $request = request();
        $id = $request->id;
        $uid = $request->uid;

        $pid = $request->id;   
        $podcast = Podcast::find($pid);
        
        $podcastCategories = $podcast->categories()->pluck('categories.id')->toArray();

        $relatedPodcastsObj = Podcast::where('id', '!=', $pid)->where('status', 1);

        if(empty($podcastCategories)){
            $relatedPodcasts2 = $relatedPodcastsObj->inRandomOrder()->take(5)->get();
        }else{

            $relatedPodcasts2 = $relatedPodcastsObj
                ->whereHas('categories', function ($query) use ($podcastCategories) {
                    $query->whereIn('categories.id', $podcastCategories);
                })
                ->inRandomOrder()
                ->take(5)
                ->get();
        }
      
      
        foreach($relatedPodcasts2 as $relatedPodcast ){
            if($relatedPodcast['thumbnail']){
                $relatedPodcast["path"] = Storage::disk('s3')->url("public/podcast/thumbnail/".$relatedPodcast['thumbnail']);
            }
            $relatedPodcast["channelName"] = $this->getChannelName($relatedPodcast["channel_id"]);
            $relatedPodcast["dateDiff"] = $relatedPodcast['created_at']->diffForHumans();
        }
        
        $liked = Like::where('podcast_id', $id)->where('user_id', $uid)->first();
        if (empty($liked)) {
            $isLiked = false;
        }else{
            $isLiked = true;
        }

        $podcast["dateDiff"] = $podcast['created_at']->diffForHumans();
        $podcast["thumbnail"] = Storage::disk('s3')->url("public/podcast/thumbnail/".$podcast['thumbnail']);
        $podcast["videoPath"] = public_path().'/podcast/'.$podcast['filename'];

        if($podcast){
            $channelId = $podcast->channel_id;
            $channelName = $this->getChannelName($channelId);
            $allCats = DB::table('category_podcast')->where('podcast_id', $id)->get();
            $allCasts = DB::table('cast_podcast')->where('podcast_id', $id)->get();
            $allTags = DB::table('podcast_tag')->where('podcast_id', $id)->get();
            $allLangs = DB::table('language_podcast')->where('podcast_id', $id)->get();
        
            $languages = array();
            foreach($allLangs as $lng){
                $lngId = $lng->language_id;
                $language = Language::find($lngId);
                $languages[] = $language->name;
            }

            $categories = array();
            foreach($allCats as $cat){
                $categoryId = $cat->category_id;
                $category = Category::find($categoryId);
                $categories[] = $category->name;
            }

            $tags = array();
            foreach($allTags as $tag){
                $tagId = $tag->tag_id;
                $tag = Tag::find($tagId);
                $tags[] = $tag->name;
            }

            $casts = array();
            foreach($allCasts as $cast){
                $castId = $cast->cast_id;
                $cast = Cast::find($castId);
                $casts[] = $cast->name;
            }
        
            $subscribed = $this->isSubscribed($uid, $channelId);

            $podcastDetails = array(
                'podcast' => $podcast,
                'channelName' => $channelName,
                'casts' => $casts,
                'categories' => $categories,
                'tags' => $tags,
                "isSubscribed" => $subscribed,
                "isLiked" => $isLiked,
                "relatedPodcasts" => $relatedPodcasts2,
                'languages' => $languages
            );
        }else{
            return response()->json([
                "error" => "something went wrong, please try again"
            ]);
        }
        
        return response()->json([
            'status' => 200,
            $podcastDetails
        ]);
    }


    public function isSubscribed($id, $channelId){
        $subscription = Subscription::where('user_id', $id)->where('channel_id', $channelId)->first();
        if (empty($subscription)) {
            return false;
        }else{
            return true;
        }
    }

    public function likePodcast(Request $request)
    {   $request = request();
        $pid = $request->pid;
        $uid = $request->uid;
        $podcast = Podcast::find($pid);
        $user = User::find($uid);
        $liked = Like::where('podcast_id', $pid)->where('user_id', $uid)->first();

        if (empty($liked)) {
            DB::beginTransaction();

            $like = new Like();
            $like->podcast_id = $pid;
            $like->user_id = $uid;
            if ($like->save()) {

                if ($podcast->increment('likes')) {
                    DB::commit();

                    return new Response([
                        'status' => 1,
                        'action' => 'liked',
                        'likesCount' => $podcast->likes,
                        "msg" => "Successfully Liked the podcast"
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

            if ($liked->delete()) {

                if ($podcast->decrement('likes')) {
                    DB::commit();

                    return new Response([
                        'status' => 1,
                        'action' => 'unliked',
                        'likesCount' => $podcast->likes,
                        "msg" => "Successfully Unliked the podcast"
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

    public function deletePodcast(Request $request)
    {
        $id = $request->id;
        $podcast = Podcast::find($id);
        $oldPodcastFileName = $podcast->thumbnail;

        if ($podcast->delete()) {
            if ($oldPodcastFileName) {
                Storage::disk('s3')->delete('public/podcast/thumbnail/' . $oldPodcastFileName);
            }
            return new Response([
                'status' => 0,
                'msg' => 'Successfully deleted'
            ]);
        } else {
            return new Response([
                'status' => 0,
                'error' => 'error, something went wrong, please try again later'
            ]);
        }
    }  
}