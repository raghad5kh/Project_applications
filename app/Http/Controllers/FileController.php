<?php

namespace App\Http\Controllers;

use GuzzleHttp\Promise\Create;
use Illuminate\Http\Request;
use App\Models\File;
use App\Models\Group;
use App\Models\Group_file;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File as FFile;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FileController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth:sanctum')
            // ->only(
            //     'upload',
            //     'rename',
            //     'book',
            //     'myFiles'
            // )
        ;
    }

    // public $based_path = "/images/";

    public function upload(Request $request)
    {
        $input_file = $request->file('file');

        $user =  Auth::guard('web')->user();
        foreach ($user->files as $file) {
            if ($file->name == $input_file->getClientOriginalName()) {
                return response()->json([
                    'message' => "The name is recently used, please change the name."
                ], 400);
            }
        }

        $file_path =  "public/files/_" . $request->user()->id . "/" . $input_file->getClientOriginalName();
        $input_file->move('public/files/_' . $request->user()->id . "/", $input_file->getClientOriginalName());

        $file = new File();
        $file->path = $file_path;
        $file->name = $request->file('file')->getClientOriginalName();
        $file->status = true;
        $file->user_id = $request->user()->id;
        $file->saveOrFail();
        return $file;

        return response()->json([
            'message' => "Uploading is done!",
            'deatails' => $file
        ], 200);
    }
    public function rename(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:20',
            'id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()], 402);
        }
        $user =  Auth::guard('web')->user();
        foreach ($user->files as $file) {
            if ($file->name == $request->name) {
                return response()->json([
                    'message' => "The name is recently used, please change the name."
                ], 400);
            }
        }

        $input_file = File::where('id', $request->id)->first();
        if (!$request->user()->id == $input_file->user_id) {
            return response()->json([
                'message' => "forbidden!"
            ], 403);
        }

        $oldFilePath = $input_file->path;

        // Get the extension
        $fileInfo = pathinfo($oldFilePath);
        $extension = $fileInfo['extension'];
        // New file path
        $newFilePath = "public/files/_" . $request->user()->id . "/" . $request->name . "." . $extension;
        try {
            // Rename the file
            FFile::move($oldFilePath, $newFilePath);
            $input_file->path = $newFilePath;
            $input_file->name = $request->name;
            $input_file->saveOrFail();
            return $input_file;
            // Output success message or perform additional actions if needed
            echo "File successfully renamed!";
        } catch (\Exception $e) {
            // Handle any exceptions that occurred during the renaming process
            echo "Error: " . $e->getMessage();
        }
    }

    //true -> file is available
    // false -> file not available

    public function book(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => "data is unvalied"], 402);
        }

        $file = File::where('id', $request->id)->first();
        $user =  Auth::guard('web')->user();
        if ($file->status == false) {
            return response()->json([
                'message' => "the file is not available !"
            ], 400);
        }

        // تشييك اذا هو موجود بمجموعة فيها هاد الفايل أو ماله المالك للملف

        // if (!($file->user_id == $user->id))

            $file->status = false;
            $file->booker = $user->id;
            $file->saveOrFail();

        $filePath =  $file->path;

        // Check if the file exists
        if (!file_exists($filePath)) {
            echo $filePath;
            return response()->json([
                'message' => "file is not found"
            ], 404);
        }

        // Set the headers for the response
        $headers = [
            'Content-Type' => Storage::mimeType('public/' .  $file->path),
            'Content-Disposition' => 'attachment; filename="' .  $file->path . '"',
        ];

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
        return response()->download($filePath, 'hi', $headers);
    }



    public function myFiles()
    {
        $user = User::find(Auth::id());

        $files = $user->files;
        return response()->json([
            'files' => $files
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

        $files = Group_file::join('groups', 'group_files.fk_group_id', '=', 'groups.id')
            ->join('files', 'group_files.fk_file_id', '=', 'files.id')
            ->get('files.*');;

        return $files;
    }

    //add file to group
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
        $group = Group::find($request->group_id)->first();
        $file = File::find($request->file_id)->first();


        if (!($user->id == $file->user_id)) {
            return response()->json(['message' => "you can't add this file"], 400);
        }

        $group_file = new Group_file();
        $group_file->fk_group_id = $request->group_id;
        $group_file->fk_file_id = $request->file_id;
        // return $group_file;
        $group_file->save();

        return  response()->json([
            'message' => "your file is added!",
            'deatails' => $file
        ], 200);
    }
    public function unBook(Request $request)
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

        if (!$user->id == $file->user_id) {
            return response()->json(['message' => "you can't do this action"], 400);
        }

    }
    //delete file from group
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

        Group_file::where('fk_group_id', $group_id)
            ->where('fk_file_id', $file_id)
            ->delete();

        // $group_file->delete();

        return response()->json([
            'message' => "deleting is done!"
        ], 200);
    }



    public function delete(Request $request)
    {
        $file = File::where('id', $request->id);

        if (!$request->user()->id == $file->user_id) {
            return response()->json([
                'message' => "you aren't the file owner"
            ], 400);
        }
        if ($file->status == false) {
            return response()->json([
                'message' => "the file is booked !"
            ], 400);
        }

        $file->delete;
        return response()->json([
            'message' => "deleting is done!"
        ], 200);
    }
}
