<?php

namespace App\Http\Controllers\Api;

use Exception;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Contract\Messaging;


class LoginController extends Controller
{

    public function login(Request $request)
    {
        // Validate the input
        $validator = Validator::make($request->all(), [
            'avatar' => 'required',
            'name' => 'required',
            'type' => 'required',
            'open_id' => 'required',
            'email' => 'max:50',
            'phone' => 'max:30'
        ]);

        if ($validator->fails()) {
            return [
                "code" => -1,
                "data" => "no valid data",
                "msg" => $validator->errors()->first()
            ];
        }

        try {

            // Get validated data
            $validated = $validator->validated();
            $map = [
                'type' => $validated['type'],
                'open_id' => $validated['open_id']
            ];

            // Check if user exists
            $result = DB::table('users')->select('avatar', 'description', 'type', 'token', 'access_token', 'online')
                ->where($map)->first();

            if (empty($result)) {
                // Register the user if they do not exist
                $validated['token'] = md5(uniqid() . rand(10000, 99999));
                $validated['created_at'] = Carbon::now();
                $validated['access_token'] = md5(uniqid() . rand(1000000, 9999999));
                $validated['expire_date'] = Carbon::now()->addDays(30);

                // Insert user and retrieve the new user
                $user_id = DB::table('users')->insertGetId($validated);
                $user_result = DB::table('users')->select('avatar', 'description', 'type', 'token', 'access_token', 'online')
                    ->where('id', '=', $user_id)->first();

                return [
                    'code' => 0,
                    'data' => $user_result,
                    'msg' => 'User has been created'
                ];
            } else {

                // update the varibale and update the database
                $access_token = md5(uniqid() . rand(1000000, 9999999));
                $expire_date = Carbon::now()->addDays(30);

                DB::table('users')->where($map)->update(
                    [
                        "access_token" => $access_token,
                        "expire_date" => $expire_date
                    ]
                );

                // send update user object to front-end
                $result->access_token = $access_token;

                return [
                    'code' => 0,
                    'data' => $result,
                    'msg' => 'User already exists'
                ];
            }
        } catch (Exception $e) {
            return
                [
                    'code' => -1,
                    'data' => 'no data avilable',
                    'msg' => (string)$e
                ];
        }
    }

    public function contact(Request $request)
    {
        $token = $request->user_token;
        $res = DB::table('users')->select(
            'avatar',
            'description',
            'online',
            'token',
            'name'
        )->where('token', '!=', $token)->get();

        return
            [
                'code' => 0,
                'data' => $res,
                'msg' => 'got all the user info'
            ];
    }

    public function send_notice(Request $request)
    {
        // Caller information
        $user_token = $request->user_token;
        $user_avatar = $request->user_avatar;
        $user_name = $request->user_name;

        // Callee information
        $to_token = $request->input('to_token');
        $call_type = $request->input('call_type');
        $to_avatar = $request->input('to_avatar');
        $to_name = $request->input('to_name');
        $doc_id = $request->input('doc_id');

        // Check if $to_token is null or empty
        if (is_null($to_token)) {
            return response()->json(['code' => 1, 'msg' => 'to_token is required'], 400);
        }

        if (is_null($doc_id)) {
            $doc_id = "";
        }

        // get other user
        $res = DB::table('users')->select("avatar", "name", "token", "fcmtoken")->where("token", "=", $to_token)->first();


        // Check if user is not found
        if (is_null($res)) {
            return response()->json(['code' => -1, 'data' => '', 'msg' => 'user does not exit'], 404);
        }

        $device_token = $res->fcmtoken;
        try {
            if (!empty($device_token)) {
                $messaging = app("firebase.messaging");
                if ($call_type == 'cancel') {

                    $message = CloudMessage::fromArray([
                        'token' => $device_token,
                        'data' => [
                            'token' => $user_token,
                            'avatar' => $user_avatar,
                            'name' => $user_name,
                            'doc_id' => $doc_id,
                            'call_type' => $call_type,
                        ]
                    ]);
                    $messaging->send($message);
                } else if ($call_type == 'voice') {

                    $message = CloudMessage::fromArray([
                        'token' => $device_token,
                        'data' => [
                            'token' => $user_token,
                            'avatar' => $user_avatar,
                            'name' => $user_name,
                            'doc_id' => $doc_id,
                            'call_type' => $call_type,
                        ],
                        'android' => [
                            'priority' => 'high',
                            'notification' => [
                                'channel_id' => 'yyy',
                                'title' => 'voice call made by' . $user_name,
                                'body' => 'Please click to answer the voice call'
                            ],
                        ]
                    ]);
                }
                $messaging->send($message);
                return response()->json(['code' => 0, 'data' => $to_token, 'msg' => 'success']);

            } else {
                return response()->json(['code' => -1, 'data' => '', 'msg' => 'device token is empty']);
            }
        } catch (\Exception $e) {
            return response()->json(['code' => -1, 'data' => '', 'msg' => (string)$e]);
        }
    }

    public function bind_fcmtoken(Request $request){
        $token = $request->user_token;
        $fcmtoken = $request->input("fcmtoken");

        if(empty($fcmtoken))
        {
            return ["code"=>-1, "data"=>"", "msg"=>"error getting the token"];
        }

       $res = DB::table('users')->where("token", "=", $token)->update(["fcmtoken"=>$fcmtoken]);

        return ["code"=>0, "data"=>$token, "msg"=>"success"];
    }

    public function upload_photo(Request $request) {
        $file = $request->file('file');

        try
        {
            $extenstion = $file->getClientOriginalExtension(); // get png or jpeg or jpg.... 
            $fullFileName = uniqid().'.'.$extenstion; // bind name with extention
            $timeDir = date("Ymd"); // create dir or folder to store image
            $file->storeAs($timeDir, $fullFileName, ["disk"=>"public"]); // set folder, name and permission and upload
            $url = env("APP_URL")."/uploads/".$timeDir.'/'.$fullFileName; // create url


            return ['code'=>0, 'data'=>$url, 'msg'=> 'success image upload'];

        }catch (Exception $e)
        {
            return ['code'=>-1, 'data'=>'', 'msg'=> 'error uploading image'];
        }
    }

    
}
