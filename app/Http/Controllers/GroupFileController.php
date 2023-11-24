<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\Group;
use App\Models\Group_file;
use App\Models\Group_member;
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

    public function showGroupFilesToAdding($group_id)
    {
        $group = Group::find($group_id)->first();
        $user =  Auth::guard('web')->user();

        // if user in group or not
        $group_member = Group_member::where('group_members.group_id', '=', $group_id)
            ->where('group_members.user_id', '=', $user->id);
        if (!$group_member) {
            return response()->json(['message' => 'you are not a member in this group']);
        }

        $files = File::leftJoin('group_files', 'files.id', '=', 'group_files.file_id')
            ->leftJoin('groups', 'group_files.group_id', '=', 'groups.id')
            ->where('files.user_id', '=', $user->id)
            ->whereNull('groups.id')
            ->orWhere('groups.id', '<>', $group_id)
            ->select('files.id', 'files.name')
            // ->where('groups.id','!=',$group_id)
            ->get();
        return response()->json([
            'data' => $files
        ], 200);
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
        $group_file = Group_file::where('group_files.file_id', '=', $request->group_id)
            ->where('grpup_files.group_id', '=', $request->file_id)
            ->get();
        if ($group_file) {
            return response()->json(['message' => "the file is already exist in this group."], 400);
        }

        $group_file = new Group_file();
        $group_file->group_id = $request->group_id;
        $group_file->file_id = $request->file_id;
        $group_file->save();

        return  response()->json([
            'message' => "your file is added!",
            'deatails' => $file
        ], 200);
    }

    // show group files
    public function showGroupFiles($group_id)
    {
        $user =  Auth::guard('web')->user();
        $group = Group::find($group_id)->first();
        if (!$group) {
            return response()->json(['message' => " the group is not exist"], 400);
        }

        //check if user is member on group
        $group_member = Group_member::where('group_members.group_id', '=', $group_id)
            ->where('group_members.user_id', '=', $user->id);
        if (!$group_member) {
            return response()->json(['message' => 'you are not a member in this group']);
        }

        $files = Group_file::join('files', 'group_files.file_id', '=', 'files.id')
            ->join('users', 'users.id', '=', 'files.booker_id')
            ->select('files.*', 'users.name as user_name')
            ->where('group_files.group_id', '=', $group_id)
            ->get('files.*');;

        return response()->json([
            'data' => $files
        ], 200);
    }

    public function showunBookedFiles($group_id)
    {
        $user =  Auth::guard('web')->user();
        $group = Group::find($group_id)->first();
        if (!$group) {
            return response()->json(['message' => "not found"], 400);
        }

        //check if user is member on group
        $group_member = Group_member::where('group_members.group_id', '=', $group_id)
            ->where('group_members.user_id', '=', $user->id);
        if (!$group_member) {
            return response()->json(['message' => 'you are not a member in this group']);
        }

        $files = Group_file::join('files', 'group_files.file_id', '=', 'files.id')
            ->where('files.status', '=', '1')
            ->where('group_files.group_id', '=', $group_id)
            ->get('files.*');

        return response()->json([
            'message' => "done!",
            'data' => $files
        ], 200);
    }

    public function removeFromGroup($group_id, $file_id)
    {
        $user =  Auth::guard('web')->user();
        $group = Group::find($group_id)->first();
        $file = File::find($file_id)->first();
        if(!$group){
            return response()->json(['message' => "group is not exist"], 400);
        } else if (!($user->id == $file->user_id)) {
            return response()->json(['message' => "you can't remove this file"], 400);
        } else if ($file->status == false) {
            return response()->json(['message' => "the file is booked!"], 400);
        }

        Group_file::where('group_id', $group_id)
            ->where('file_id', $file_id)
            ->delete();

        return response()->json([
            'message' => "deleting is done!"
        ], 200);
    }
}
