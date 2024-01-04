<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\Group;
use App\Models\Group_file;
use App\Models\Group_member;
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

        $read = $this->groupFileService->read($group_id, $file_id);
        return $read;

    }

    public function showGroupFilesToAdding($group_id)
    {

        $show = $this->groupFileService->showGroupFilesToAdding($group_id);
        return $show;

    }

    public function addToGroup(Request $request)
    {
        $add = $this->groupFileService->addToGroup($request);
        return $add;
    }

    // show group files
    public function showGroupFiles($group_id)
    {
        $showGroup = $this->groupFileService->showGroupFiles($group_id);
        return $showGroup;
    }


    public function showunBookedFiles($group_id)
    {
        $unBooked = $this->groupFileService->showunBookedFiles($group_id);
        return $unBooked;
    }

    public function removeFromGroup($group_id, $file_id)
    {
        $remove = $this->groupFileService->removeFromGroup($group_id, $file_id);
        return $remove;

    }
}
