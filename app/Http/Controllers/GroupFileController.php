<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\Group;
use App\Models\Group_file;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Validator;

class GroupFileController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function showGroupFilesToAdding(Request $request, $group_id)
    {
        // $validator =Validator::make($request->all,[
        //     'gro'
        // ]);
        $group = Group::find($group_id)->first();
        $user =  Auth::guard('web')->user();

        $files = File::leftJoin('group_files', 'group_files.file_id', '=', 'files.id')
        ->whereNull('group_files.file_id')
        ->where('files.user_id', '=', $user->id)
        ->get('files.*')
        ;


        return $files;
    }

    
    public function addToGroup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_id' => 'required',
            'group_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => "data is unvalied"], 400);
        }

        $user =  Auth::guard('web')->user();
        $file = File::find($request->file_id)->first();


        if (!($user->id == $file->user_id)) {
            return response()->json(['message' => "you can't add this file"], 400);
        }

        $group_file = new Group_file();
        $group_file->group_id = $request->group_id;
        $group_file->file_id = $request->file_id;
        // return $group_file;
        $group_file->save();

        return  response()->json([
            'message' => "your file is added!",
            'deatails' => $file
        ], 200);
    }

    //show group files
    public function showGroupFiles($id)
    {
        $user =  Auth::guard('web')->user();
        $group = Group::find($id)->first();
        if (!$group) {
            return response()->json(['message' => "not found"], 400);
        }

        //check if user is member on group

        $files = Group_file::join('groups', 'group_files.group_id', '=', 'groups.id')
            ->join('files', 'group_files.file_id', '=', 'files.id')
            ->get('files.*');;

        return $files;
    }

        //show group files
        // public function showGroupFiles($id)
        // {
        //     $user =  Auth::guard('web')->user();
        //     $group = Group::find($id)->first();
        //     if (!$group) {
        //         return response()->json(['message' => "not found"], 400);
        //     }
    
        //     //check if user is member on group
    
        //     $files = Group_file::join('groups', 'group_files.group_id', '=', 'groups.id')
        //         ->join('files', 'group_files.file_id', '=', 'files.id')
        //         ->get('files.*');;
    
        //     return $files;
        // }

    public function removeFromGroup($group_id, $file_id)
    {
        $user =  Auth::guard('web')->user();
        $group = Group::find($group_id)->first();
        $file = File::find($file_id)->first();

        if (!($user->id == $file->user_id)) {
            return response()->json(['message' => "you can't remove this file"], 400);
        } else if ($file->status == false) {
            return response()->json(['message' => "you can't remove the file because it is booked!"], 400);
        }

        Group_file::where('group_id', $group_id)
            ->where('file_id', $file_id)
            ->delete();

        // $group_file->delete();

        return response()->json([
            'message' => "deleting is done!"
        ], 200);
    }
}
