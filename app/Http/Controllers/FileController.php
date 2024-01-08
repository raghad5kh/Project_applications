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
        if ($validator->fails() || !$request->hasfile('file')) {
            return response()->json(['message' => $validator->errors()], 400);
        }
        $authenticatedUser =  $request->user();
        if (!$authenticatedUser) {
            return response()->json(['message' => 'Unauthorized'], 401);
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
        if (isset($result['status'])) {
            return response()->json(['message' => $result['message']], $result['status']);
        }
        return response()->json(['message' => $result['message']], 200);
    }

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
        $result = $this->fileService->bookFile($user, $request->group_id, $request->file_ids);
        if (isset($result['status'])) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        if (!file_exists($result)) {
            return response()->json([
                'message' => "'Failed to create ZIP archive"
            ], 400);
        }
        return response()->download($result)->deleteFileAfterSend(true);
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
        $result = $this->fileService->unBookFile($user, $request->file_id, $request->group_id);
        if (isset($result['status'])) {
            return response()->json(['message' => $result['message']], $result['status']);
        }
        return response()->json(['message' => $result['message']], 200);
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
        if (isset($result['status'])) {
            return response()->json(['message' => $result['message']], $result['status']);
        }
        return response()->json(['message' => $result['message']], 200);
    }
}
