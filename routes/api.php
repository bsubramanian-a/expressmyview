<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('reg', 'UsersController@reg');
Route::post('login', 'UsersController@login');
Route::post('getuser', 'UsersController@getuser');
Route::post('updateAccountSettings', 'UsersController@updateAccountSettings');
Route::post('mySubscriptionsAPI', 'SubscriptionsController@mySubscriptionsAPI');
Route::post('createChannel', 'ChannelsController@createChannel');
Route::post('updateChannel', 'ChannelsController@updateChannel');
Route::post('deleteChannel', 'ChannelsController@deleteChannel');
Route::post('updatePodcast', 'PodcastsController@updatePodcast');
Route::post('deletePodcast', 'PodcastsController@deletePodcast');
Route::post('generateToken', 'LiveStreamsController@generateToken');
Route::post('uploadMedia', 'PodcastsController@uploadMedia');
Route::post('channelsByUid', 'ChannelsController@channelsByUid');
Route::post('getAllChannels', 'ChannelsController@getAllChannels');
Route::post('allLanguages', 'UsersController@allLanguages');
Route::post('getUploadPodcastDetails', 'UsersController@getUploadPodcastDetails');
Route::post('uploadPodcastDetails', 'PodcastsController@uploadPodcastDetails');
Route::post('updatePodcastDetails', 'PodcastsController@updatePodcastDetails');
Route::post('getPodcastDetails', 'PodcastsController@getPodcastDetails');
Route::post('likePodcast', 'PodcastsController@likePodcast');
Route::post('subscribePodcast', 'SubscriptionsController@subscribePodcast');
Route::post('subscribeChannel', 'SubscriptionsController@subscribeChannel');
Route::post('getHomeDatas', 'HomeController@getHomeDatas');
Route::get('getAllVideoPodcasts', 'PodcastsController@getAllVideoPodcasts');
Route::post('getMyAccountDatas', 'UsersController@getMyAccountDatas');
Route::post('getMyChannels', 'UsersController@getMyChannels');
Route::post('channelDetails', 'UsersController@channelDetails');
Route::post('changePassword', 'UsersController@changePassword');
Route::post('forgotpassword', 'UsersController@forgotpassword');
Route::post('verifyotp', 'UsersController@verifyotp');
Route::post('relatedPodcasts', 'PodcastsController@relatedPodcasts');
Route::post('changepasswordafterverified', 'UsersController@changepasswordafterverified');