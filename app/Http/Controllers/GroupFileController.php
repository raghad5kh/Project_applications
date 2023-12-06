<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\Group;
use App\Models\Group_file;
use App\Models\Group_member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use Illuminate\Support\Facades\Validator;

class GroupFileController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }
    public function read($group_id, $file_id)
    {
        $user =  Auth::user();
        $file = File::where('id', $file_id)->first();

        // return $file;
        if (!$file) {
            return response()->json([
                'message' => "the file is not exist !"
            ], 400);
        }
        if ($file->status == false && $user->id != $file->booker_id) {
            return response()->json([
                'message' => "the file is not available !"
            ], 400);
        }
        // check if file exist in this group
        $exist = Group_file::where('group_id', '=', $group_id)
            ->where('file_id', '=', $file_id)
            ->exists();
        if (!$exist) {
            return response()->json([
                'message' => "the file is not exist in this group !"
            ], 400);
        }

        // user in group that file is existed in it
        $check = Group_member::join('group_files', 'group_files.group_id', '=', 'group_members.group_id')
            ->where('group_members.user_id', '=', $user->id)
            ->where('group_files.file_id', '=', $file_id);
        if (!$check) {
            return response()->json(['message' => "this file isn't available"], 400);
        }

        // Check if the file exists
        if (!file_exists($file->path)) {
            return response()->json([
                'message' => "file is not found"
            ], 400);
        }

        // return response()->json([
        //     'file' => $file,
        //     'user' => $user
        // ]);
        // Set the headers for the response
        // $headers = [
        //     'Content-Type' => Storage::mimeType('public/' .  $file->path),
        //     'Content-Disposition' => 'attachment; filename="' .  $file->name . '"',
        // ];
        // Create and return the streamed response
        // return response()->stream(
        //     function () use ($filePath) {
        //         $stream = fopen($filePath, 'r');
        //         fpassthru($stream);
        //         fclose($stream);
        //     },
        //     200,
        //     $headers
        // );
        // return response() ->download($filePath, $file->name, $headers);

        $file_content = file_get_contents($file->path, true);
        $file_name = basename($file->path);

        //store in history
        (new HistoryController())->store($group_id, $file_id, $user->id, 'read',true);
        return response()->json([
            'message' => 'done',
            'file_name' => $file_name,
            'file_content' => $file_content
        ]);
    }
    public function showGroupFilesToAdding($group_id)
    {
        $user =  Auth::user();

        // if user in group or not
        $group_member = Group_member::where('group_members.group_id', '=', $group_id)
            ->where('group_members.user_id', '=', $user->id)
            ->exists();

        if (!$group_member) {
            return response()->json(['message' => 'you are not a member in this group'], 400);
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
            'file_ids' => 'required|array',
            'file_ids.*' => 'required|numeric',
            'group_id' => 'required|numeric'
        ]);

        

        if ($validator->fails()) {
            return response()->json(['message' => "data is unvalied"], 400);
        }
        

        $user =  Auth::user();
        
        // if user in group or not
        $group_member = Group_member::where('group_members.group_id', '=', $request->group_id)
            ->where('group_members.user_id', '=', $user->id)
            ->exists();
        
        if (!$group_member) {
            return response()->json(['message' => 'you are not a member in this group'], 400);
        }
        
        foreach ($request->file_ids as $id) {
            $group_file = Group_file::where('group_files.file_id', '=', $id)
                ->where('group_files.group_id', '=', $request->group_id)
                ->exists();

            if ($group_file) {
                return response()->json(['message' => "the file is already exist in this group."], 400);
            }

            $group_file = new Group_file();
            $group_file->group_id = $request->group_id;
            $group_file->file_id = $id;
            $group_file->save();
        }
        foreach ($request->file_ids as $id) {
            (new HistoryController)->store($request->group_id, $id, $user->id, 'add', true);
        }

        return  response()->json([
            'message' => "your file is added!",
        ], 200);
    }

    // show group files
    public function showGroupFiles($group_id)
    {
        $user =  Auth::user();
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
            ->leftJoin('users', 'users.id', '=', 'files.booker_id')
            ->select('files.id','files.name','files.status', 'group_files.created_at', 'users.name as booker_name')
            ->where('group_files.group_id', '=', $group_id)
            ->get();

        return response()->json([
            'data' => $files
        ], 200);
    }

    public function showunBookedFiles($group_id)
    {
        $user =  Auth::user();
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
        $user =  Auth::user();
        $group = Group::find($group_id)->first();
        $file = File::where('id', $file_id)->first();

        // return response()->json([
        //     'file' => $file,
        //     'group' => $group
        // ]);
        if (!$group) {
            return response()->json(['message' => "group is not exist"], 400);
        } else if (!($user->id == $file->user_id)) {
            return response()->json(['message' => "you can't remove this file"], 400);
        } else if ($file->status == false) {
            return response()->json(['message' => "the file is booked!"], 400);
        }

        Group_file::where('group_id', $group_id)
            ->where('file_id', $file_id)
            ->delete();
        
        (new HistoryController)->store($group_id, $file_id, $user->id, 'delete', true);

        return response()->json([
            'message' => "deleting is done!"
        ], 200);
    }
}
