<?php
namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesCommands;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;

use App\Model\App;
use App\Model\Role;
use App\Model\User;
use App\Model\UserWechat;
use Cache;
use Config;
use DB;
use Dingo\Api\Routing\Helpers;
use Auth;

abstract class Controller extends BaseController
{

    use DispatchesCommands, ValidatesRequests;
    use Helpers;

    function cacheApps($app_id)
    {
        $appsArray = App::all();
        foreach($appsArray as $v) {
            $apps[$v['id']] = Cache::get(Config::get('cache.apps') . $v['id'], function() use ($v) {
                $cacheData = array('id' => $v['id'], 'name' => $v['name'], 'title' => $v['title']);
                Cache::forever(Config::get('cache.apps') . $v['id'], $cacheData);
                return $cacheData;
            });
        }
        return $apps[$app_id];
    }

    function cacheRoles($role_id)
    {
        $rolesArray = Role::all();
        foreach($rolesArray as $v) {
            $roles[$v['id']] = Cache::get(Config::get('cache.roles') . $v['id'], function() use ($v) {
                $cacheData = array('id' => $v['id'], 'name' => $v['name'], 'title' => $v['title']);
                Cache::forever(Config::get('cache.roles') . $v['id'], $cacheData);
                return $cacheData;
            });
        }
        return $roles[$role_id];
    }

    function cacheUsers()
    {
        // 用户详细信息字段
        $userFieldsArray = DB::table('user_fields')->get(array('id', 'name', 'title', 'description'));
        foreach ($userFieldsArray as $v) {
            $userFields[$v->id] = array('name' => $v->name, 'title' => $v->title);
        }

        // 用户基本信息，初始化详细信息
        $usersArray = User::get(array('id', 'username', 'email', 'phone'))->toArray();
        foreach ($usersArray as $v) {
            $users[$v['id']] = array('user_id' => $v['id'], 'username' => $v['username'], 'email' => $v['email'], 'phone' => $v['phone']);
            foreach ($userFields as $value) {
                $users[$v['id']]['details'][$value['name']] = array('title' => $value['title'], 'value' => '');
            }
        }

        // 用户详细信息
        $userInfoArray = DB::table('user_info')->get(array('user_id', 'field_id', 'value'));
        foreach ($userInfoArray as $v) {
            $users[$v->user_id]['details'][$userFields[$v->field_id]['name']]['value'] = $v->value;
        }

        foreach($users as $v) {
            Cache::forever(Config::get('cache.users') . $v['user_id'], $v);
        }
    }

    function cacheWechat()
    {
        $usersArray = UserWechat::get(array('user_id', 'unionid', 'openid', 'nickname', 'sex', 'language', 'city', 'province', 'country', 'headimgurl'));
        foreach ($usersArray as $v) {
            Cache::forever(Config::get('cache.wechat.openid') . $v['openid'], $v);
            Cache::forever(Config::get('cache.wechat.user_id') . $v['user_id'], $v['openid']);
        }
    }

    public function accessToken()
    {
        // $authCode = $this->api->get('api/oauth/getAuthCode?client_id=' . env('client_id') . '&response_type=code&redirect_uri=' . urlencode(env('redirect_uri')));
        if (!($accessToken = Cache::get(Config::get('cache.api.access_token') . Auth::id()))) {
            $authCode = $this->api
                ->with(['client_id' => env('client_id'), 'response_type' => 'code', 'redirect_uri' => env('redirect_uri')])
                ->get('api/oauth/getAuthCode');
            $accessToken = $this->api
                ->with(['client_id' => env('client_id'), 'client_secret' => env('client_secret'),
                    'grant_type' => 'authorization_code', 'redirect_uri' => env('redirect_uri'), 'code' => $authCode])
                ->post('api/oauth/getAccessToken');
            $accessToken = $accessToken['data'];
            $accessToken['timestamp'] = time();
            Cache::put(Config::get('cache.api.access_token') . Auth::id(), $accessToken, $accessToken['expires_in'] / 60);
        } elseif ( time() - $accessToken['timestamp'] < 10 * 60) {
            $accessToken = $this->api
                ->with(['client_id' => env('client_id'), 'client_secret' => env('client_secret'),
                    'grant_type' => 'refresh_token', 'refresh_token' => $accessToken['refresh_token']])
                ->post('api/oauth/getAccessToken');
            $accessToken = $accessToken['data'];
            $accessToken['timestamp'] = time();
            Cache::put(Config::get('cache.api.access_token') . Auth::id(), $accessToken, $accessToken['expires_in'] / 60);
        }
        return $accessToken['access_token'];
    }
}
