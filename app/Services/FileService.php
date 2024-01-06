<?php


namespace App\Services;

use App\Http\Controllers\HistoryController;
use App\Models\File;
use Illuminate\Support\Facades\File as FFile;
use App\Models\Group_member;
use App\Models\History;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class FileService extends Service
{
    public function uploadFile($user, $input_file)
    {
        foreach ($user->files as $file) {
            if ($file->name == $input_file->getClientOriginalName()) {
                return ['message' => "The name is recently used, please change the name.", 'status' => 400];
            }
        }
        $file_path = storage_path("app/public/files/_" . $user->id . "/" . $input_file->getClientOriginalName());
        $input_file->move(storage_path('app/public/files/_' . $user->id . "/"), $input_file->getClientOriginalName());
        $file = new File();
        $file->path = $file_path;
        $file->name = $input_file->getClientOriginalName();
        $file->status = true;
        $file->user_id = $user->id;
        $file->save();

        return ['message' => "Uploading is done!"];
    }

    public function edaitFile($user, $file_id, $group_id, $input_file)
    {
        $file = File::where('id', $file_id)->first();

        $check = Group_member::join('group_files', 'group_files.group_id', '=', 'group_members.group_id')
            ->where('group_members.user_id', '=', $user->id)
            ->where('group_files.file_id', '=', $file_id);

        if (!$check) {
            return response()->json(['message' => "this file isn't available", 'status' => 400], 400);
        }

        if ($file->status != false || $user->id != $file->booker_id) {
            return response()->json(['message' => "forbidden !", 'status' => 400], 400);
        }

        if ($file->name != $input_file->getClientOriginalName()) {
            return response()->json(['message' => "the name and extension must be similar to the orginal file !", 'status' => 400], 400);
        }

        $file_path = storage_path("app/public/files/_" . $user->id . "/temp/" . $input_file->getClientOriginalName());
        $input_file->move(storage_path('app/public/files/_' . $user->id . "/temp/"), $input_file->getClientOriginalName());
        $file->copy_path = $file_path;
        $file->save();
        (new HistoryController)->store($group_id, $file->id, $user->id, 'Update', false);
        return ['message' => "Uploading is done!"];
    }

    public function bookFile($user, $group_id, $file_ids)
    {
        $paths = [];
        foreach ($file_ids as $id) {
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
        //store in history
        foreach ($file_ids as $id) {
            (new HistoryController)->store($group_id, $id, $user->id, 'Reserve', false);
        }

        return $zipFilePath;
    }

    public function unBookFile($user, $file_id, $group_id)
    {
        $file = File::find($file_id);

        if (!$user->id == $file->booker_id) {
            return response()->json(['message' => "you can't do this action"], 400);
        }

        if (file_exists($file->copy_path)) {
            $move = FFile::move($file->copy_path, $file->path);
        }

        $file->status = true;
        $file->booker_id = null;
        $file->copy_path = null;
        $file->save();

        History::where('file_id', '=', $file_id)->update(['proved' => true]);

        //store in the history
        (new HistoryController)->store($group_id, $file_id, $user->id, 'Unreserve', true);

        return response()->json(['message' => "file unBooked successfully"], 200);
    }

    public function myFiles($user)
    {

        $files = File::join('users', 'users.id', '=', 'files.user_id')
            ->leftJoin('users as booker_users', 'files.booker_id', '=', 'booker_users.id')
            ->select('files.id', 'files.name as file_name', 'users.name as user_name', 'files.status', 'booker_users.name as booker_name')
            ->where('users.id', '=', $user->id)->get();
        return $files;
    }

    public function deleteFile($user, $id)
    {
        $file = File::where('id', $id)->first();
        //check if the authenticated user is the owner of the file
        if (!$user->id == $file->user_id) {
            return response()->json([
                'message' => "you aren't the file owner"
            ], 400);
        }

        //check if the file is booked 
        if ($file->status == false) {
            return response()->json([
                'message' => "the file is booked!"
            ], 400);
        }

        //check if the file is located inside a group
        $fileWithGroups = $file->group_file()->exists();
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
