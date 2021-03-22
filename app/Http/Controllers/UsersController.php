<?php

namespace App\Http\Controllers;

use App\LiveStream;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use PragmaRX\Countries\Package\Countries;
use Illuminate\Support\Facades\Storage;
use App\User;
use App\License;
use App\Cast;
use App\Tag;
use App\Language;
use App\Channel;
use App\Category;
use App\UserProfile;
use App\Podcast;
use \App\Mail\Enquiry;
use Mail;
use Carbon;
use App\Subscription;

class UsersController extends Controller
{
    public function account()
    {
        $authenticatedUser = Auth::user();
        $podcasts = $authenticatedUser->podcasts()->where('status', 1);
        $podcasts2 = clone $podcasts;
        $streams = LiveStream::where('user_id', $authenticatedUser->id)->latest()->take(4)->get();

        $videos = $podcasts->where('file_type', 'video')->latest()->take(4)->with('channel')->get();
        $audios = $podcasts2->where('file_type', 'audio')->latest()->take(4)->with('channel')->get();

        $viewData = [
            'user' => $authenticatedUser,
            'channels' => $authenticatedUser->channels,
            'videos' => $videos,
            'audios' => $audios,
            'streams' => $streams,
        ];
        return view('users.account', $viewData);
    }

    public function settings()
    {
        $user = Auth::user();
        $user->load('profile');

        $countries = new Countries();
        $allCountries = $countries->all()->sortBy('name.common')->pluck('name.common');

        if (!empty($user->profile->country)) {
            $states = $countries->where('name.common', $user->profile->country)->first()->hydrateStates()->states->sortBy('name')->pluck('name', 'postal');
        } else {
            $states = array();
        }

//        if (!empty($user->profile->state)) {
//            $cities = $countries->where('name.common', $user->profile->billing_country)->first()->hydrateStates()->states->sortBy('name')->pluck('name', 'postal');
//        } else {
//            $cities = null;
//        }

        $viewData = [
            'user' => $user,
            'countries' => $allCountries,
            'states' => $states
        ];
        return view('users.settings', $viewData);
    }

    public function updateSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'phone' => 'required|string',
            'address' => 'required|string',
            'city' => 'required|string',
            'state' => 'required|string',
            'country' => 'required|string',
            'zip' => 'required|string',
            'avatar' => 'nullable|image',
        ]);

        if ($validator->fails()) {
            $errors = $validator->getMessageBag()->toArray();
            $errorsArray = array();
            foreach ($errors as $error) {
                $errorsArray[] = $error[0];
            }

            return new Response(array(
                'status' => 0,
                'message' => '<ul><li>' . implode('</li><li>', $errorsArray) . '</li></ul>'
            ));
        } else {
            $user = Auth::user();

            if ($request->hasFile('avatar')) {
                $avatarFilename = null;
                $oldAvatarFilename = $user->profile->avatar;
                if ($request->file('avatar')->store('public/users/avatar')) {
                    $avatarFilename = $request->file('avatar')->hashName();
                    if (!empty($oldAvatarFilename)) {
                         Storage::disk('s3')->delete('public/users/avatar' . '/' . $oldAvatarFilename);
//                         Storage::delete(storage_path('public/users/avatar') . '/' . $oldAvatarFilename);
                    }

                } else {
                    return new Response([
                        'status' => 0,
                        'message' => 'An error occurred. Please try again.'
                    ]);
                }
            } else {
                $avatarFilename = $user->profile->avatar;
            }

            DB::beginTransaction();
            try {
                $user->name = $request->input('name');
                $user->hide_email = $request->input('hide_email') == 'yes' ? 1 : 0;
                $user->save();

                $profile = $user->profile;
                $profile->phone = $request->input('phone');
                $profile->address = $request->input('address');
                $profile->city = $request->input('city');
                $profile->state = $request->input('state');
                $profile->country = $request->input('country');
                $profile->zip = $request->input('zip');
                $profile->avatar = $avatarFilename;
                $profile->save();

                DB::commit();

                return new Response(array(
                    'status' => 1,
                    'message' => 'Profile updated successfully.'
                ));
            } catch (\Exception $exception) {
                DB::rollBack();

                return new Response([
                    'status' => 0,
                    'message' => 'An error occurred. Please try again.'
                ]);
            }
        }
    }

    public function getStates(Request $request)
    {
        $countryName = $request->input('country');
        if (empty($countryName)) {
            return response()->json([
                'status' => 0,
                'message' => 'Please specify a country.'
            ]);
        } else {
            $countries = new Countries();
            $states = $countries->where('name.common', $countryName)->first()->hydrateStates()->states->sortBy('name')->pluck('name', 'postal');
            $statesOptions = '';
            foreach ($states as $state) {
                $statesOptions .= '<option value="' . $state . '">' . $state . '</option>';
            }

            return response()->json([
                'status' => 1,
                'states' => $statesOptions
            ]);
        }
    }

    public function editPassword()
    {
        $user = Auth::user();
        return view('users.password', compact('user'));
    }

    public function updatePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => 'required|min:8|confirmed|string',
        ]);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator);
        } else {
            $user = Auth::user();
            if (Hash::check($request->input('current_password'), $user->password)) {
                $user->password = Hash::make($request->input('password'));
                if ($user->save()) {
                    return redirect()->back()->with('success_msg', 'Password updated successfully.');
                } else {
                    return redirect()->back()->with('error_msg', 'Password could not be updated.');
                }
            } else {
                $validator->getMessageBag()->add('current_password', 'Password provided is incorrect.');
                return redirect()->back()->withErrors($validator);
            }
        }
    }

    public function login(Request $request) {
        // $this->assignHeaders();
        $req = $request->all();
        $email = $req["email"];
        $password = $req["password"];

        if($email == "" || $password == ""){
            $user = array(
                "error" => "credentials are wrong"
            );
            return response()->json($user, 200);
        }

        $user = User::where("email",$email)->get();
        if ($user->count() == 0) {
            $user = array(
                "error" => "credentials are wrong"
            );
            return response()->json($user, 200);
        }
        if (\Hash::check($password, $user[0]->password)) {
            $user_profile = Userprofile::where("user_id", $user[0]->id)->get();
            if($user_profile[0]['avatar']){
                $user_profile[0]["imagePath"] = Storage::disk('s3')->url("public/users/avatar/".$user_profile[0]['avatar']);
            }
            return response()->json([$user, $user_profile], 200);
        } else {
            $user = array(
                "error" => "credentials are wrong"
            );
            return response()->json($user, 200);
        }
    }

    public function getMyChannels(Request $request)
    {
        $userid    = $request->userid;
        $myChannels = Channel::where('user_id', $userid)->orderBy('created_at', 'DESC')->get();
        foreach ($myChannels as $myChannel ){
            if($myChannel['logo']){
                $myChannel["logoPath"] = Storage::disk('s3')->url("public/users/logo/".$myChannel['logo']);
            }
            if($myChannel['banner']){
                $myChannel["bannerPath"] = Storage::disk('s3')->url("public/users/banner/".$myChannel['banner']);
            }
            $allPodcastsCount = Podcast::where('channel_id', $myChannel['id'])->count();
            $myChannel["channelName"] = $this->getChannelName($myChannel["channel_id"]);
            $myChannel["dateDiff"] = $myChannel['created_at']->diffForHumans();
            $myChannel["allPodcastsCount"] = $allPodcastsCount;
        }

        if(empty($myChannels)){
            return response()->json([
                'error' => "No data available right now, please try again later"
            ]);
        }else{
            $viewData = array(
                'myChannels' => $myChannels
            );
        }

        return response()->json([
            'status' => 200,
            $viewData
        ]);
    }


    public function channelDetails(Request $request)
    {
        $channelId = $request->channel_id;
        $uid = $request->uid;
        $channelDetails = Channel::find($channelId);
        if($channelDetails->logo){
            $channelDetails["logoPath"] = Storage::disk('s3')->url("public/users/logo/".$channelDetails['logo']);
        }
        if($channelDetails['banner']){
            $channelDetails["bannerPath"] = Storage::disk('s3')->url("public/users/banner/".$channelDetails['banner']);
        }

        $subscribed = $this->isSubscribed($uid, $channelId);

        $allPodcastsCount = Podcast::where('channel_id', $channelId)->count();
        $allPodcasts = Podcast::where('channel_id', $channelId)->get();

        foreach ($allPodcasts as $allPodcast ){
            if($allPodcast['thumbnail']){
                $allPodcast["path"] = Storage::disk('s3')->url("public/podcast/thumbnail/".$allPodcast['thumbnail']);
            }
            $allPodcast["dateDiff"] = $allPodcast['created_at']->diffForHumans();
        }

        if(empty($channelDetails)){
            return response()->json([
                'error' => "No data available right now, please try again later"
            ]);
        }else{
            $viewData = array(
                'channelDetails' => $channelDetails,
                'allPodcastsCount' => $allPodcastsCount,
                'allPodcasts' => $allPodcasts, 
                "isSubscribed" => $subscribed,
            );
        }

        return response()->json([
            'status' => 200,
            $viewData
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

    public function getChannelName($channelId){
        $channel = Channel::find($channelId);

        if($channel){
            return $channel->name;
        }else{
            return "";
        }
    }

    public function forgotpassword(Request $request) {
        // $this->assignHeaders();
        $req = $request->all();
        $email = $req["email"];
        $user = User::where("email",$email)->get();
        $email = str_replace("\xE2\x80\x8B", "", $email); 
        $id = $user[0]->id;

        if ($user->count() == 0) {
            $user = array(
                "error" => "Email doesn't exist, Please enter a valid email or contact admin to register"
            );
            return response()->json($user, 200);
        }
        
        if($user[0]->type == "admin"){
            $user = array(
                "error" => "User not registered"
            );
            return response()->json($user, 200);
        }

        if( $user[0]->status == "0"){
            $user = array(
                "error" => "Your account has been blocked, please contact admin"
            );
            return response()->json($user, 200);
        }

        $otp = [
            'user_code' => mt_rand(100000,999999)
        ];

        $user = User::find($id);
        $user->otp = $otp['user_code'];
        $user->save();

        Mail::send('email', ['otp' => $otp['user_code']], function($message) use ($email){
            $message->to($email)->subject('Reset Password');
            $message->from(config('mail.username'), 'Admin');
        });
    
        if (Mail::failures()) {
            $error = array(
                "error" => "Something went wrong, please try again later"
            );
            return response()->json($error, 200);
        }

        return response()->json([ 'message' => "Email Sent Successfully" ]);
    }

    public function verifyotp(Request $request) {
        $req = $request->all();
        $otp = $req["otp"];
        $email = $req["email"];
        $users = User::where("email", $email)->get();

        $id = $users[0]->id;

        $user = User::find($id);
        
        if( $users[0]->status == "0"){
            $user = array(
                "error" => "Your account has been blocked, please contact admin"
            );
            return response()->json($user, 200);
        }

        if($otp['verificationcode'] != $users[0]->otp){
                $user = array(
                    "error" => "Enter valid OTP"
                );
                return response()->json($user, 200);
        }

        return response()->json("Successfully verified", 200);
    }

    public function changepasswordafterverified(Request $request) {
        $req = $request->all();
        $data = $req["password"];
        $password = $data['password'];
        $email = $req["email"];
        $users = User::where("email", $email)->get();

        $id = $users[0]->id;

        $user = User::find($id);
        
        if( $users[0]->status == "0"){
            $user = array(
                "error" => "Your account has been blocked, please contact admin"
            );
            return response()->json($user, 200);
        }
       
        $user->password = bcrypt($password);
        $user->save();

        return response()->json("Password Successfully Changed", 200);
    }
    
    public function broker()
    {
        return Password::broker();
    }

    protected function sendResetLinkFailedResponse(Request $request, $response)
    {
        return back()->withErrors(
            ['email' => trans($response)]
        );
    }

    protected function sendResetLinkResponse($response)
    {
        return back()->with('status', trans($response));
    }

    protected function validateEmail(Request $request)
    {
        $this->validate($request, ['email' => 'required|email']);
    }

    public function getMyAccountDatas(Request $request)
    {
        $userid    = $request->userid;
        $myVideos = Podcast::where('user_id', $userid)->where('file_type', "video")->orderBy('created_at', 'DESC')->take(2)->get();

        foreach ($myVideos as $myVideo ){
            if($myVideo['thumbnail']){
                $myVideo["path"] = Storage::disk('s3')->url("public/podcast/thumbnail/".$myVideo['thumbnail']);
            }
            $myVideo["channelName"] = $this->getChannelName($myVideo["channel_id"]);
            $myVideo["dateDiff"] = $myVideo['created_at']->diffForHumans();
        }

        $myAudios = Podcast::where('user_id', $userid)->where('file_type', "audio")->orderBy('created_at', 'DESC')->take(2)->get();

        foreach ($myAudios as $myAudio ){
            if($myAudio['thumbnail']){
                $myAudio["path"] = Storage::disk('s3')->url("public/podcast/thumbnail/".$myAudio['thumbnail']);
            }
            $myAudio["channelName"] = $this->getChannelName($myAudio["channel_id"]);
            $myAudio["dateDiff"] = $myAudio['created_at']->diffForHumans();
        }

        $myChannels = Channel::where('user_id', $userid)->orderBy('created_at', 'DESC')->take(7)->get();
        foreach ($myChannels as $myChannel ){
            if($myChannel['logo']){
                $myChannel["path"] = Storage::disk('s3')->url("public/users/logo/".$myChannel['logo']);
            }
            $myChannel["channelName"] = $this->getChannelName($myChannel["channel_id"]);
            $myChannel["dateDiff"] = $myChannel['created_at']->diffForHumans();
        }

        if(empty($myVideos) && empty($myAudios) && empty($myChannels)){
            return response()->json([
                'error' => "No data available right now, please try again later"
            ]);
        }else{
            $viewData = array(
                'myVideos' => $myVideos,
                'myAudios' => $myAudios,
                'myChannels' => $myChannels
            );
        }

        return response()->json([
            'status' => 200,
            $viewData
        ]);
    }

    public function getUploadPodcastDetails(Request $request)
    {
        $userid    = $request->userid;
        $languages = Language::all();
        $casts = Cast::all();
        $tags = Tag::all();
        $licenses = License::all();
        $categories = Category::all();
        $channels = Channel::where("user_id",$userid)->get();
        if ($channels->count() == 0) {
            $channels = array(
                "error" => "You have not created any channel, Please create a new channel and upload"
            );
        }

        return response()->json(["languages" => $languages, "channels" => $channels, "casts" => $casts, "tags" => $tags, "licenses" => $licenses, "categories" => $categories], 200);
    }

    public function allLanguages(Request $request)
    {
        $languages = Language::all();
        return response()->json($languages, 200);
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'currentPassword' => 'required|string',
            'newPassword' => 'required|min:8|string',
        ]);
        if ($validator->fails()) {
            $user = array(
                "error" => "Password length should be minimum 8 characters"
            );
            return response()->json($user, 200);
        } else {
            $id = $request->input('id');
            $user = User::find($id);
            if (Hash::check($request->input('currentPassword'), $user->password)) {
                $user->password = Hash::make($request->input('newPassword'));
                if ($user->save()) {
                    return response()->json('Password updated successfully');
                } else {
                    $user = array(
                        "error" => "Password could not be updated, please try again later"
                    );
                    return response()->json($user, 200);
                }
            } else {
                $user = array(
                    "error" => "Password provided is incorrect"
                );
                return response()->json($user, 200);
            }
        }
    }

    public function updateAccountSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'phone' => 'required|string',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
            'country' => 'nullable|string',
            'zip' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            $errors = $validator->getMessageBag()->toArray();
            $errorsArray = array();
            foreach ($errors as $error) {
                $errorsArray[] = $error[0];
            }

            return new Response(array(
                'status' => 0,
                'message' => '<ul><li>' . implode('</li><li>', $errorsArray) . '</li></ul>'
            ));
        } else {
            $id = $request->input('id');
            $user = User::find($id);

            if ($request->input('avatar')) {
                $img = $request->input('avatar');
                $imageName = null;
                $imageName = $user->profile->avatar;
                if (preg_match('/^data:image\/(\w+);base64,/', $img)) {
                    Storage::disk('s3')->delete('public/users/avatar/'. $imageName);
                    $data = substr($img, strpos($img, ',') + 1);
                    $data = base64_decode($data);
                    $type = explode('/', mime_content_type($img))[1];
                    $currentDateTime = Carbon\Carbon::now();
                    $currentDateTimeString = $currentDateTime->toDateTimeString();
                    $imageName = $currentDateTime."_".$id.".".$type;
                    $p = Storage::disk('s3')->put('public/users/avatar/' . $imageName, $data, 'public');
                }
                else {
                    $imageName = $user->profile->avatar;
                }
            } else {
                $imageName = $user->profile->avatar;
            }

            DB::beginTransaction();
            try {
                $user->name = $request->input('name');
                $user->hide_email = $request->input('hide_email') == 'true' ? 1 : 0;
                $user->save();

                $profile = $user->profile;
                $profile->phone = $request->input('phone');
                $profile->address = $request->input('address');
                $profile->city = $request->input('city');
                $profile->state = $request->input('state');
                $profile->country = $request->input('country');
                $profile->zip = $request->input('zip');
                $profile->avatar = $imageName;
                $profile->save();

                if($profile->avatar){
                    $profile->avatar = Storage::disk('s3')->url("public/users/avatar/".$profile->avatar);
                }
                DB::commit();

                return new Response(array(
                    'status' => 1,
                    'message' => 'Profile updated successfully.',
                    "user_profile" => $profile
                ));
            } catch (\Exception $exception) {
                DB::rollBack();

                return new Response([
                    'status' => 0,
                    'error' => 'An error occurred. Please try again.'
                ]);
            }
        }
    }

    public function getuser(Request $request) {
        // $this->assignHeaders();
        $req = $request->all();
        $id = $req["id"];
        $user = User::find($id);
        $user_profile = UserProfile::where("user_id", $id)->get();
        if($user_profile[0]['avatar']){
            $user_profile[0]["imagePath"] = Storage::disk('s3')->url("public/users/avatar/".$user_profile[0]['avatar']);
        }
        $viewData = [
            'user' => $user,
            'user_profile' => $user_profile
        ];
        return response()->json($viewData, 200);
    }
   
    public function reg(Request $request) {
        // $this->assignHeaders();
        $rules = [
            'name' => 'required',
            'email'    => 'unique:users|required',
            'phone' => 'unique:user_profiles|required',
            'password' => 'required',
        ];
    
        if($request->name == "" || $request->email == "" || $request->password == "" || $request->phone == ""){
            $msg = array(
                "error" => "Values are Empty"
            );
            return response()->json($msg, 200);
        }

        $input     = $request->only('name', 'email', 'password', 'phone');
        $validator = Validator::make($input, $rules);
    
        if ($validator->fails()) {
            $msg = array(
                "error" => "Email or mobile number already registered"
            );
            return response()->json($msg, 200);
        }
        $name = $request->name;
        $email    = $request->email;
        $password = $request->password;
        $phone = $request->phone;
        $user     = User::create(['name' => $name, 'email' => $email, 'password' => \Hash::make($password), 'ip' => '::1', 'user_type' => 'user']);
        $user = User::where("email",$email)->get();
        $user_profile = UserProfile::create(['user_id' => $user[0]->id, 'phone' => $phone]);
        $user_profile = UserProfile::where("user_id", $user[0]->id)->get();
        return response()->json(['user' => $user, 'user_profile' => $user_profile], 200);
    }

}
