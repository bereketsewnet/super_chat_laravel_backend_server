<?php
namespace App\Http\Controllers\Api;

use Exception;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;


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

    try
    {

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
                "access_token"=>$access_token,
                "expire_date"=>$expire_date
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

    }catch (Exception $e)
    {
        return 
        [
            'code'=>-1,
            'data'=> 'no data avilable',
            'msg'=>(String)$e
        ];
    }

  
}
}