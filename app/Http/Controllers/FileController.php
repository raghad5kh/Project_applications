<?php

namespace App\Http\Controllers;

use GuzzleHttp\Promise\Create;
use Illuminate\Http\Request;
use App\Models\File;
use App\Models\Group;
use App\Models\Group_file;
use App\Models\Group_member;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File as FFile;
use App\Models\User;
use Dotenv\Store\File\Paths;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Input\Input;
use ZipArchive;

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
        $check = Group_member::join('group_files', 'group_files.group_id', '=', 'group_members.group_id')
            ->where('group_members.user_id', '=', $user->id)
            ->where('group_files.file_id', '=', $id);
        if ($user->id != $file->user_id) {
            return response()->json([
                'message' => "the file is not available !"
            ], 400);
        } else if (!$check) {
            return response()->json(['message' => "this file isn't available"], 400);
        }

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
        return response()->download($filePath, $file->name, $headers);
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
            return response()->json(['message' => $validator->errors()], 400);
        }
        $paths = [];
        $user =  $request->user();

        foreach ($request->files_id as $id) {
            $file = File::where('id', $id)->first();
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
            //copy files to temp folder
            // FFile::copy($file->path, storage_path("app/public/files/_" . $request->user()->id . "/temp/" . $file->name));
        }
        $zipFileName = 'downloaded_files_' . time() . '.zip';
        $zipFilePath = storage_path("app/public/{$zipFileName}");


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
            return response()->json(['error' => 'Failed to create ZIP archive'], 400);
        }

        // Set the headers for the response
        $headers = [
            'Content-Type' => Storage::mimeType('public/' .  $file->path),
            'Content-Disposition' => 'attachment; filename="' .  $file->name . '"',
        ];
        // return "hi";

        // Create and return the streamed response
        // return response()->stream(
        //     function () use ($zipFilePath) {
        //         // foreach ($paths as $filePath) {
        //         $stream = fopen($zipFilePath, 'r');
        //         fpassthru($stream);
        //         fclose($stream);
        //     },
        //     200,
        //     $headers
        // );
        return response()->download($zipFilePath, $zipFileName, $headers)->deleteFileAfterSend(true);

//        return response(['message' => 'done'])->download($zipFilePath, $zipFileName)->deleteFileAfterSend('true');
        // return $zipFilePath;
        // return response()->download($zipFilePath, 'files', $headers);
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
        $user =  Auth::guard('web')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $files = File::join('users', 'users.id', '=', 'files.user_id')
            ->select('files.name as file_name', 'users.name as user_name', 'files.status')
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
