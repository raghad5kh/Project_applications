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
use Symfony\Component\Console\Input\Input;

class FileController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function read($id)
    {
        $file = File::where('id', $id)->first();
        $user =  Auth::guard('web')->user();
        if ($file->status == false) {
            return response()->json([
                'message' => "the file is not available !"
            ], 400);
        }

        // user is owner or in group that file is existed in it

        $filePath =  $file->path;

        // Check if the file exists
        if (!file_exists($filePath)) {
            echo $filePath;
            return response()->json([
                'message' => "file is not found"
            ], 400);
        }

        // Set the headers for the response
        $headers = [
            'Content-Type' => Storage::mimeType('public/' .  $file->path),
            'Content-Disposition' => 'attachment; filename="' .  $file->name . '"',
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

    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'files.*' => 'required|file'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()], 400);
        }

        // $input_file = $request->file('file');
        $user =  Auth::guard('web')->user();

        if (!$request->hasfile('files')) {
            return response()->json(['message' => $validator->errors()], 400);
        }
        foreach ($request->file('files') as $input_file) {
            foreach ($user->files as $file) {
                if ($file->name == $input_file->file()->getClientOriginalName()) {
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
        }

        // return $file;

        return response()->json([
            'message' => "Uploading is done!",
            // 'deatails' => $file
        ], 200);
    }

    public function edit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_id' => 'required',
            'file' => 'required|file'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()], 400);
        }

        $file = File::where('id', $request->file_id)->first();
        $user =  Auth::guard('web')->user();

        if ($file->status != false || $user->id != $file->booker_id) {
            return response()->json(['message' => "forbidden !"], 400);
        }
        $input_file = $request->file('file');
        // return  $input_file->getClientOriginalName();
        if ($file->name != $input_file->getClientOriginalName()) {
            return response()->json(['message' => "the name and extension must be similar to the orginal file !"], 400);
        }

        $file_path =  "public/files/_" . $request->user()->id . "/temp/" . $input_file->getClientOriginalName();
        $input_file->move('public/files/_' . $request->user()->id . "/temp/", $input_file->getClientOriginalName());
        $file->copy_path = $file_path;
        $file->save();

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
            return response()->json(['message' => $validator->errors()], 400);
        }
        $user =  Auth::guard('web')->user();

        $input_file = File::where('id', $request->id)->first();

        foreach ($user->files as $file) {
            if ($file->name == $request->name) {
                return response()->json([
                    'message' => "The name is recently used, please change the name."
                ], 400);
            }
        }

        if (!$request->user()->id == $input_file->user_id) {
            return response()->json([
                'message' => "forbidden!"
            ], 400);
        } else if ($input_file->status == false) {
            return response()->json([
                'message' => "The file is booked !."
            ], 400);
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

    // true -> file is available
    // false -> file not available

    public function book(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => "data is unvalied"], 400);
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
        $file->booker_id = $user->id;
        $file->saveOrFail();

        $filePath =  $file->path;

        // Check if the file exists
        if (!file_exists($filePath)) {
            echo $filePath;
            return response()->json([
                'message' => "file is not found"
            ], 400);
        }

        // Set the headers for the response
        $headers = [
            'Content-Type' => Storage::mimeType('public/' .  $file->path),
            'Content-Disposition' => 'attachment; filename="' .  $file->name . '"',
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


    public function unBook(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()], 400);
        }

        $user =  Auth::guard('web')->user();
        $file = File::find($request->file_id)->first();

        if (!$user->id == $file->booker_id) {
            return response()->json(['message' => "you can't do this action"], 400);
        }
        // return file_exists($file->copy_path); 
        if (file_exists($file->copy_path)) {
            $move = FFile::move($file->copy_path, $file->path);
            echo $move;
        }

        $file->status = true;
        $file->booker_id = null;
        $file->copy_path = null;
        $file->save();

        return response()->json(['message' => "done"], 200);
    }


    //return the name of booker
    public function myFiles()
    {
        $user = User::find(Auth::id());

        // $files = $user->files;
        $files=File::join('users','users.id','=','files.booker_id')
            ->select('files.name as file_name','users.name as user_name','files.status')
            ->get()
        ;
        return response()->json([
            'data' => $files
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
