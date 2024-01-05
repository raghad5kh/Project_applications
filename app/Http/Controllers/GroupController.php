<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\Group;
use App\Models\Group_member;
use App\Models\Group_file;
use App\Models\User;
use App\Services\GroupService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class GroupController extends Controller
{
    public function __construct(private GroupService $groupService, private UserService $userService)
    {
        $this->middleware('auth:sanctum');
    }

    //Add Group
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|unique:groups',
        ]);

        $user = $request->user(); // Get the authenticated user
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $result = $this->groupService->store($data['name'], $user->id);

        return response()->json(['message' => $result['message'], 'group' => $result['group']], 200);
    }


    //------------------------------------------------------------------------------------------------------------------------

    public function groupMember(Request $request)
    {
        $validator = Validator::make($request->all(), ['group_id' => 'required','user' => 'required']);
        if ($validator->fails()) {
            return response()->json(['message' => "data is not valid"], 400);
        }
        // Check if the user is authenticated
        $authenticatedUser = $request->user();
        if (!$authenticatedUser) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $result = $this->groupService->addMember($authenticatedUser->id, $request->group_id, $request->user);
        if (isset($result['status'])) {
            return response()->json(['message' => $result['message']], $result['status']);
        }
        return response()->json(['message' => $result['message'], 'group_member' => $result['member']], 200);
    }


    //------------------------------------------------------------------------------------------------------------------------

    public function destroy($id)
    {
        $authenticatedUser = Auth::user();
        if (!$authenticatedUser) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        // Delete the group and associated group members (groupMembers will be deleted via the deleting event)
        $result = $this->groupService->deleteGroup($authenticatedUser->id, $id);
        if (isset($result['status'])) {
            return response()->json(['message' => $result['message']], $result['status']);
        }
        return response()->json(['message' => $result['message']], 200);
    }

    //------------------------------------------------------------------------------------------------------------------------

    public function allGroups()
    {
        //get the authenticated user
        $user =  Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 400);
        }
        $result = $this->groupService->getUserGroups($user->id);
        return response()->json(['message' => $result['message'], 'groups' => $result['allGroups']], 200);
    }


    //------------------------------------------------------------------------------------------------------------------------

    // users for specific group
    public function usersGroup($id)
    {
        $user =  Auth::user();//get the authenticated user
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 400);
        }
        $result = $this->groupService->getGroupMembers($user->id, $id);
        if (isset($result['status'])) {
            return response()->json(['message' => $result['message']], $result['status']);
        }
        return response()->json([
            'message' => $result['message'],
            'group_name' => $result['group']['name'],
            'admin_username' => $result['admin']['adminUsername'],
            'admin_email' => $result['admin']['adminEmail'],
            'group_members' => $result['userDetails'],
        ], 200);
    }

    //------------------------------------------------------------------------------------------------------------------------

    public function deleteMember($group_id, $user_id)
    {
        $authenticatedUser = Auth::user();
        if (!$authenticatedUser) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        
        $result = $this->groupService->deleteMember($authenticatedUser->id, $group_id, $user_id);
        if (isset($result['status'])) {
            return response()->json(['message' => $result['message']], $result['status']);
        }
        return response()->json(['message' => $result['message']], 200);
    }

    //------------------------------------------------------------------------------------------------------------------------

}