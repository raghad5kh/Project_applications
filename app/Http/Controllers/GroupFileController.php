<?php

namespace App\Http\Controllers;

use App\Services\GroupFileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class GroupFileController extends Controller
{

    public function __construct(private GroupFileService $groupFileService)
    {
        $this->middleware('auth:sanctum');
    }

    public function read($group_id, $file_id)
    {
        $authenticatedUser = Auth::user(); //get the authenticated user
        if (!$authenticatedUser) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $result = $this->groupFileService->read($authenticatedUser->id, $group_id, $file_id);
        if (isset($result['status'])) {
            return response()->json(['message' => $result['message']], $result['status']);
        }
        return response()->json([
            'file_name' => $result['file_name'],
            'file_content' => $result['file_content']
        ], 200);

    }

    public function showGroupFilesToAdding($group_id)
    {
        $authenticatedUser = Auth::user(); //get the authenticated user
        if (!$authenticatedUser) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $result = $this->groupFileService->showGroupFilesToAdding($group_id, $authenticatedUser->id);
        if (isset($result['status'])) {
            return response()->json(['message' => $result['message']], $result['status']);
        }
        return response()->json([
            'data' => $result['data']
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
        $authenticatedUser = Auth::user();//get the authenticated user
        if (!$authenticatedUser) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $result = $this->groupFileService->addToGroup($request->group_id, $authenticatedUser->id, $request->file_ids);
        if (isset($result['status'])) {
            return response()->json(['message' => $result['message']], $result['status']);
        }
        return response()->json([
            'message' => $result['message']
        ], 200);;
    }

    // show group files
    public function showGroupFiles($group_id)
    {
        $authenticatedUser = Auth::user();//get the authenticated user
        if (!$authenticatedUser) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $result = $this->groupFileService->showGroupFiles($group_id,$authenticatedUser->id);
        if (isset($result['status'])) {
            return response()->json(['message' => $result['message']], $result['status']);
        }
        return response()->json([
            'data' => $result['data']
        ], 200);
    }


    public function showunBookedFiles($group_id)
    {
        $authenticatedUser = Auth::user();//get the authenticated user
        if (!$authenticatedUser) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $result = $this->groupFileService->showunBookedFiles($group_id,$authenticatedUser->id);
        if (isset($result['status'])) {
            return response()->json(['message' => $result['message']], $result['status']);
        }
        return response()->json([
            'message' => $result['message'],
            'data' => $result['data']
        ], 200);
    }

    public function removeFromGroup($group_id, $file_id)
    {
        $authenticatedUser = Auth::user();//get the authenticated user
        if (!$authenticatedUser) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $result = $this->groupFileService->removeFromGroup($authenticatedUser->id, $group_id, $file_id);
        if (isset($result['status'])) {
            return response()->json(['message' => $result['message']], $result['status']);
        }
        return response()->json([
            'message' => $result['message']
        ], 200);

    }
}
