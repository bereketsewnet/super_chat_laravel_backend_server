<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckUser
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Retrieve the Authorization header
        $authorization = $request->header('Authorization');

        // Check if Authorization header exists
        if (!$authorization) {
            return response()->json(
                [
                    'code' => 401,
                    'message' => 'Authentication failed',
                ],
                401
            );
        }
        // $authorezition
        // ltrim($authorization, 'hello); => world;
        $access_token = trim(ltrim($authorization, 'Bearer'));
        $res_user = DB::table('users')->where('access_token', $access_token)->select(
            'id',
            'name',
            'avatar',
            'type',
            'token',
            'access_token',
            'expire_date'
        )->first();

        if (empty($res_user)) {
            return response(['code' => 401, 'message' => 'User does not exit'], 401);
        }

        $expire_date = $res_user->expire_date;
        if (empty($expire_date)) {
            return response(['code' => 401, 'message' => 'you must login'], 401);
        }

        if($expire_date<Carbon::now()) {
            return response(['code' => 401, 'message' => 'your token has expired. you must login again'], 401);
        }

        $addTime = Carbon::now()->addDays(5);
        if($expire_date<$addTime){
            $add_expire_date = Carbon::now()->addDays(30);
            DB::table('users')
            ->where('access_token', $access_token)
            ->update(['expire_date'=>$add_expire_date]);
        }

        // Merge user data into the request object
        $request->merge([
            'user_id' => $res_user->id,
            'user_type' => $res_user->type,
            'user_name' => $res_user->name,  
            'user_avatar' => $res_user->avatar,  
            'user_token' => $res_user->token,  
        ]);

        // Allow the request to proceed
        return $next($request);
    }
}
