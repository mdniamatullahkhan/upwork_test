<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller; 
use App\User; 
use Auth; 
use Validator;
use File;
use Storage;

class UserController extends Controller{

    public $successStatus = 200;

    public function login(){ 
        if(Auth::attempt(['email' => request('email'), 'password' => request('password')])){ 
            $user = Auth::user(); 
            $success['token'] =  $user->createToken('MyApp')-> accessToken; 
            return response()->json(['success' => $success], $this-> successStatus); 
        } 
        else{ 
            return response()->json(['error'=>'Unauthorised'], 401); 
        } 
    }

    public function register(Request $request){ 
        $validator = Validator::make($request->all(), [ 
            'name' => 'required', 
            'email' => 'required|email',
            'password' => 'required', 
            'c_password' => 'required|same:password', 
        ]);

        if ($validator->fails()) { 
            return response()->json(['error'=>$validator->errors()], 401);            
        }
        $input = $request->all(); 
        $input['password'] = bcrypt($input['password']); 
        $user = User::create($input); 
        $success['token'] =  $user->createToken('MyApp')->accessToken; 
        $success['name'] =  $user->name;
        return response()->json(['success'=>$success], $this->successStatus); 
    }

    public function profile_update(Request $req, $id){ 
        $user = Auth::user();
        $validator = Validator::make($req->all(), [
            'user_name' => 'required|min:4|max:20|unique:users',
            'pin' => 'required',
            'password' => 'required',
            'avatar' => 'required|dimensions:min_width=256,min_height=256'
        ]);

        if ($validator->fails()) { 
            return response()->json(['error'=>$validator->errors()], 401);            
        }        

        $filename = "";
        if($req->hasFile('avatar')) { //check file is getting or not..
            $file = $req->file('avatar');
            $filename=uniqid(time() . '_'    ).".".$file->getClientOriginalExtension();
            Storage::disk('public')->put($filename,File::get($file));
            if(Storage::disk('public')->exists($filename)) {
               info("file is store successfully : ".$filename); 
               $input['avatar'] = $filename;
            }else { 
               info("file is not found :- ".$filename);
            }
        }
        
        $cq = User::where("id", $id)->where("pin", $req->pin)->exists();
        if($cq){
            $dt = User::find($id);
            $dt->pin = 1;
            $dt->password = bcrypt($req->password); 
            $dt->user_name = $req->username; 
            $dt->avatar = $filename; 
            $dt->registered_at = date('Y-m-d H:i'); 
            $dt->save();
            return response()->json(['error' => false, 'message' => 'Your profile successfully updated'], $this-> successStatus);   
        } else {
            return response()->json(['error' => true, 'message' => 'Invalid Pin'], $this-> successStatus); 
        }
    }

    public function create(Request $request){ 
        $user = Auth::user(); 
        $type = $user->user_role;
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email'
        ]);

        if ($validator->fails()) { 
            return response()->json(['error'=>$validator->errors()], 401);            
        }        

        if($type == 'admin'){
            $rand = rand(100000,999999);
            $input = $request->all();
            $input['user_role'] =  'user';
            $input['pin'] =  $rand;
            $input['password'] = bcrypt(123456); 
            $user = User::create($input);
            $userid = User::find(\DB::table('users')->max('id'));
            //send email:
            $to = $input['email'];
            $subject = "Access Pin";
            $txt = "Please click <a href='http://kemne.xyz/profile-update/".$userid."'>here</a> to create profile and use the following access pin - ". $rand;
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= 'From: UpworkTest<upworktest@kemne.xyz>' . "\r\n";
            mail($to,$subject,$txt,$headers);
            return response()->json(['error' => false], $this-> successStatus); 
        } else {
            return response()->json(['error' => true, "message" => "You are not authorized to perform the action"], 401); 
        }    
    }
}
