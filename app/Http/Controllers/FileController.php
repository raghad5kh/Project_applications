<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\File;
use App\Models\Group;
use App\Models\Group_file;
use App\Models\Group_member;
use App\Models\History;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File as FFile;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use ZipArchive;


class FileController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }
    public function calculateDaysDifference($date)
    {
        // Parse the input date using Carbon
        $inputDate = Carbon::parse($date);

        // Get the current date
        $currentDate = Carbon::now();

        // Calculate the difference in days
        $differenceInDays = $currentDate->diffInDays($inputDate);

        return $differenceInDays;
    }
    public function index()
    {
        $histories= History::where('event','=','Reserve')
            ->where('proved','=',false)
            ->get();
        // return $histories;
        foreach($histories as $history){
            echo $history->created_at . "    "  . $this->calculateDaysDifference($history->created_at)  . "\n";
        }
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

        // return response()->json([
        //     "user" => $user,
        //     "file" => $input_file,
        //     'user_files' => $user->files,
        //     'save-file' => $file
        // ]);

        
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

        (new HistoryController)->store($request->group_id, $request->file_id, $user->id, 'Update', false);

        return response()->json([
            'message' => "Uploading is done!",
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
    //     $user =  Auth::user();
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
    //     // return 'hi';
    //     return response()->download($filePath, 'hi', $headers);
    // }

    public function book(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_ids' => 'required|array',
            'file_ids.*' => 'required|numeric',
            'group_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()], 400);
        }
        
        $paths = [];
        $user =  $request->user();
        
        foreach ($request->file_ids as $id) {
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
        $zip = new ZipArchive();
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

        foreach ($request->file_ids as $id) {
            (new HistoryController)->store($request->group_id, $id, $user->id, 'Reserve', false);
        }
        

        // Set the headers for the response
        $headers = [
            'Content-Type' => Storage::mimeType('public/' .  $file->path),
            'Content-Disposition' => 'attachment; filename="' .  $file->name . '"',
        ];

        return response()->download($zipFilePath)->deleteFileAfterSend(true);
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
            $move = File::move($file->copy_path, $file->path);
        }

        $file->status = true;
        $file->booker_id = null;
        $file->copy_path = null;
        $file->save();

        History::where('file_id', '=', $request->file_id)->update(['proved' => true]);


        (new HistoryController)->store($request->group_id, $request->file_id, $user->id, 'Unreserve', true);

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
            ->leftJoin('users as booker_users', 'files.booker_id', '=', 'booker_users.id')
            ->select('files.id','files.name as file_name', 'users.name as user_name', 'files.status','booker_users.name as booker_name')
            ->where('users.id','=',$user->id)->get();
        return response()->json([
            'data' => $files
        ], 200);
    }

    public function delete($id)
    {
        $user =  Auth::user();
        $file = File::where('id', $id)->first();

        // return response()->json(['file' => $file]);
        if (!$user->id == $file->user_id) {
            return response()->json([
                'message' => "you aren't the file owner"
            ], 400);
        }

        if ($file->status == false) {
            return response()->json([
                'message' => "the file is booked !"
            ], 400);
        }

        $fileWithGroups = $file->group_file()->exists();
        // return response()->json(['file' => $fileWithGroups]);

        if ($fileWithGroups) {
            return response()->json([
                'message' => "This file cannot be deleted because it is located inside a group!"
            ], 400);
        }
        if (file_exists($file->path)) {
            Storage::delete($file->path);
        }
        $file->delete();

        return response()->json([
            'message' => "deleting is done!"
        ], 200);
    }
}
