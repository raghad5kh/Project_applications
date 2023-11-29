<?php

namespace App\Http\Controllers;

use App\Aspects\FileAspect;
use Illuminate\Http\Request;
use App\Models\File;
use App\Models\Group_file;
use App\Models\Group_member;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File as FFile;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Facades\Auth;
use ZipArchive;

#[FileAspect]
class FileController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }
    public function index(Request $request)
    {
        $dt = new DateTime();
        echo $dt->format('d-m-Y');
    }
    public function read($file_id, $group_id)
    {
        $file = File::where('id', $file_id)->first();
        $user =  Auth::user();
        if ($file->status == false && $user->id != $file->booker_id) {
            return response()->json([
                'message' => "the file is not available !"
            ], 400);
        }
        // check if file exist in this group
        $exist=Group_file::where('group_id','=',$group_id)
            ->where('file_id','=',$file_id)
            ->exists();
        if(!$exist){
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

        $file_content = file_get_contents($file->path,true);

        //store in history
        (new HistoryController())->store($group_id, $file_id, $user->id, 'read');

        return response()->json([
            'message' => 'done',
            'file_content' => $file_content
        ]);
    }

    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()], 400);
        }

        $input_file = $request->file('file');
        $user =  $request->user();

        if (!$request->hasfile('file')) {
            return response()->json(['message' => $validator->errors()], 400);
        }

        foreach ($user->files as $file) {
            if ($file->name == $input_file->getClientOriginalName()) {
                return response()->json([
                    'message' => "The name is recently used, please change the name."
                ], 400);
            }
        }

        $file_path = storage_path("app/public/files/_" . $request->user()->id . "/" . $input_file->getClientOriginalName());
        $input_file->move(storage_path('app/public/files/_' . $request->user()->id . "/"), $input_file->getClientOriginalName());
        $file = new File();
        $file->path = $file_path;
        $file->name = $request->file('file')->getClientOriginalName();
        $file->status = true;
        $file->user_id = $request->user()->id;
        $file->save();

        // $file = new File();
        // $file->path = $file_path;
        // $file->name = $request->file('file')->getClientOriginalName();
        // $file->status = "alaa";
        // $file->user_id = $request->user()->id;
        // $file->save();

        return response()->json([
            'message' => "Uploading is done!",
        ], 200);
    }

    public function edit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_id' => 'required',
            'group_id' => 'required',
            'file' => 'required|file'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()], 400);
        }
        $file = File::where('id', $request->file_id)->first();
        $user = $request->user();

        $check = Group_member::join('group_files', 'group_files.group_id', '=', 'group_members.group_id')
            ->where('group_members.user_id', '=', $user->id)
            ->where('group_files.file_id', '=', $request->file_id);
        if (!$check) {
            return response()->json(['message' => "this file isn't available"], 400);
        }


        if ($file->status != false || $user->id != $file->booker_id) {
            return response()->json(['message' => "forbidden !"], 400);
        }
        $input_file = $request->file('file');
        // return  $input_file->getClientOriginalName();
        if ($file->name != $input_file->getClientOriginalName()) {
            return response()->json(['message' => "the name and extension must be similar to the orginal file !"], 400);
        }

        $file_path = storage_path("app/public/files/_" . $request->user()->id . "/temp/" . $input_file->getClientOriginalName());
        $input_file->move(storage_path('app/public/files/_' . $request->user()->id . "/temp/"), $input_file->getClientOriginalName());
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
        $user = $request->user();

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
        $newFilePath = storage_path("app/public/files/_" . $request->user()->id . "/" . $request->name . "." . $extension);
        try {
            // Rename the file
            FFile::move($oldFilePath, $newFilePath);
            $input_file->path = $newFilePath;
            $input_file->name = $request->name;
            $input_file->saveOrFail();
            return response()->json([
                'message' => 'done',
                'file' => $input_file
            ], 200);
        } catch (\Exception $e) {
            return "Error: " . $e->getMessage();
        }
    }

    // true -> file is available
    // false -> file not available


    // public function book(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'id' => 'required'
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json(['message' => "data is unvalied"], 400);
    //     }

    //     $file = File::where('id', $request->id)->first();
    //     $user =  Auth::guard('web')->user();
    //     if ($file->status == false) {
    //         return response()->json([
    //             'message' => "the file is not available !"
    //         ], 400);
    //     }

    //     // تشييك اذا هو موجود بمجموعة فيها هاد الفايل أو ماله المالك للملف

    //     $check = Group_member::join('group_files', 'group_files.group_id', '=', 'group_members.group_id')
    //         ->where('group_members.user_id', '=', $user->id)
    //         ->where('group_files.file_id', '=', $request->id);
    //     if ($user->id != $file->user_id) {
    //         return response()->json([
    //             'message' => "the file is not available !"
    //         ], 400);
    //     } else if (!$check) {
    //         return response()->json(['message' => "this file isn't available"], 400);
    //     }



    //     // Check if the file exists
    //     $filePath =  $file->path;
    //     if (!file_exists($filePath)) {
    //         echo $filePath;
    //         return response()->json([
    //             'message' => "file is not found"
    //         ], 400);
    //     }
    //     $file->status = false;
    //     $file->booker_id = $user->id;
    //     $file->saveOrFail();

    //     // Set the headers for the response
    //     $headers = [
    //         'Content-Type' => Storage::mimeType('public/' .  $file->path),
    //         'Content-Disposition' => 'attachment; filename="' .  $file->name . '"',
    //     ];

    //     // Create and return the streamed response
    //     // return response()->stream(
    //     //     function () use ($filePath) {
    //     //         $stream = fopen($filePath, 'r');
    //     //         fpassthru($stream);
    //     //         fclose($stream);
    //     //     },
    //     //     200,
    //     //     $headers
    //     // );
    //     return response()->download($filePath, 'hi', $headers);
    // }

    public function book(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'files_id.*' => 'required',
            'group_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => "data is unvalied"], 400);
        }
        $paths = [];

        foreach ($request->files_id as $id) {
            $file = File::where('id', $id)->first();
            $user =  $request->user();
            if ($file->status == false) {
                return response()->json([
                    'message' => "the file is not available !"
                ], 400);
            }
            // تشييك اذا هو موجود بمجموعة فيها هاد الفايل أو ماله المالك للملف
            $check = Group_member::join('group_files', 'group_files.group_id', '=', 'group_members.group_id')
                ->where('group_members.user_id', '=', $user->id)
                ->where('group_files.file_id', '=', $id);
            if (!$check) {
                return response()->json(['message' => "this file isn't available"], 400);
            }
            // Check if the file exists
            $filePath =  $file->path;
            if (!file_exists($filePath)) {
                return response()->json([
                    'message' => "file is not found"
                ], 400);
            }
            $paths[] = $filePath;
            $file->status = false;
            $file->booker_id = $user->id;
            $file->saveOrFail();
        }

        $zipFileName = 'downloaded_files_' . time() . '.zip';
        $zipFilePath = storage_path("app/public/{$zipFileName}");
        //copy files to temp folder 
        foreach ($paths as $path) {
            FFile::copy($path, storage_path("app/public/files/_" . $request->user()->id . "/temp/"));
        }

        // Create a new ZipArchive
        $zip = new ZipArchive;
        if ($zip->open($zipFilePath, ZipArchive::CREATE) === true) {
            foreach ($paths as $path) {
                // Add each generated DOCX file to the ZIP archive
                $docxFileName = basename($path);
                $zip->addFile($path, $docxFileName);
            }

            $zip->close();
        } else {
            return response()->json(['error' => 'Failed to create ZIP archive'], 500);
        }

        // Set the headers for the response
        // $headers = [
        //     'Content-Type' => Storage::mimeType('public/' .  $file->path),
        //     'Content-Disposition' => 'attachment; filename="' .  $file->name . '"',
        // ];

        // Create and return the streamed response
        // return response()->stream(
        //     function () use ($paths) {
        //         foreach ($paths as $filePath) {
        //             $stream = fopen($filePath, 'r');
        //             fpassthru($stream);
        //             fclose($stream);
        //         }
        //     },
        //     200,
        //     $headers
        // );
        return response()->download($zipFilePath, $zipFileName)->deleteFileAfterSend(true);
        // return response()->download($zipPath, 'files', $headers)->deleteFileAfterSend(true);
    }

    public function unBook(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_id' => 'required',
            'group_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()], 400);
        }

        $user = $request->user();
        $file = File::find($request->file_id);

        // return $file;
        if (!$user->id == $file->booker_id) {
            return response()->json(['message' => "you can't do this action"], 400);
        }
        // return file_exists($file->copy_path); 
        if (file_exists($file->copy_path)) {
            $move = FFile::move($file->copy_path, $file->path);
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
        $user =  Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 400);
        }

        $files = File::join('users', 'users.id', '=', 'files.user_id')
            ->select('files.id', 'files.name as file_name', 'users.name as user_name', 'files.status')
            ->get();
        return response()->json([
            'data' => $files
        ], 200);
    }

    public function delete(Request $request, $id)
    {
        $file = File::find($id)->first();

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

        if (file_exists($file->path)) {
            FFile::delete($file->path);
        }
        $file->delete();

        return response()->json([
            'message' => "deleting is done!"
        ], 200);
    }
}
