<?php


namespace App\Services;

use App\Models\File;

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
}