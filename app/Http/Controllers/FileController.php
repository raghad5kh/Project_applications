<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\File;
use App\Models\Group;
use App\Models\Group_file;
use App\Models\Group_member;
use App\Models\History;
use App\Services\FileService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File as FFile;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use ZipArchive;


class FileController extends Controller
{
    public function __construct(private FileService $fileService)
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


    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file'
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()], 400);
        }
        $authenticatedUser =  $request->user();
        if (!$authenticatedUser) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        if (!$request->hasfile('file')) {
            return response()->json(['message' => $validator->errors()], 400);
        }

        $result = $this->fileService->uploadFile($authenticatedUser, $request->file('file'));
        if (isset($result['status'])) {
            return response()->json(['message' => $result['message']], $result['status']);
        }
        return response()->json([
            'message' => $result['message']
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

        $user = $request->user(); //get the authenticated user
        $result = $this->fileService->edaitFile($user, $request->file_id, $request->group_id, $request->file('file'));

        return $result;
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


        $user =  $request->user();
        $zipFilePath = $this->fileService->bookFile($user, $request->group_id, $request->file_ids);
        // Set the headers for the response

        if (!file_exists($zipFilePath)) {
            return response()->json([
                'message' => "'Failed to create ZIP archive"
            ], 400);
        }
        // $headers = [
        //     'Content-Type' => Storage::mimeType('public/' .  basename($zipFilePath)),
        //     'Content-Disposition' => 'attachment; filename="' . basename($zipFilePath) . '"',
        // ];
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

        $user = $request->user(); //get the authenticated user
        $result = $this->fileService->unBookFile($user, $request->file->id, $request->group_id);
        return $result;
    }


    public function myFiles()
    {
        //get the authenticated user
        $user =  Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 400);
        }

        //get all user file with the file booker name 
        $files = $this->fileService->myFiles($user);

        return response()->json([
            'data' => $files
        ], 200);
    }

    public function delete($id)
    {
        //get the authenticated user
        $user =  Auth::user();
        $result = $this->fileService->deleteFile($user, $id);
        return $result;
    }
}
