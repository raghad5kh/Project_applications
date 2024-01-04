<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\Group;
use App\Models\Group_member;
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

        $group = $this->groupService->store($data['name'], $user->id);

        return response()->json(['message' => 'Group created successfully', 'group' => $group]);
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
        
        $group = $this->groupService->getGroupById($request->group_id);
        $userToAdd = $this->userService->getUserByEmailOrUsername($request->user);
        if (!$group || !$userToAdd) { // Check if the group and user exist
            return response()->json(['message' => 'Group or user not found'], 404);
        }

        // Check if the authenticated user is the group admin who created the group
        if ($authenticatedUser->id !== $group->admin_id) {
            return response()->json(['message' => 'Unauthorized. You are not the group admin who created the group.'], 401);
        }

        $isMemberExist = $this->groupService->isMemberExist($group->id, $userToAdd->id);
        if ($isMemberExist) {//check if the user want to add is already exist in the group
            return response()->json(['message' => 'this user is already exist in this group'], 400);
        }

        $groupMember = $this->groupService->groupMember($group, $userToAdd->id);
        return response()->json(['message' => 'Group member added successfully', 'group_member' => $groupMember]);
    }


    //------------------------------------------------------------------------------------------------------------------------

    public function destroy($id)
    {
        $user = Auth::user();

        // Retrieve the group based on the provided ID
        $group = Group::find($id);

        // Check if the group exists
        if (!$group) {
            return response()->json(['message' => 'Group not found'], 404);
        }

        // Check if the authenticated user owns the group
        if ($user->id !== $group->admin_id) {
            return response()->json(['message' => 'You are not an admin of this group'], 401);
        }

        // Check if there are associated files with status '0' for the group (there is no booked file in the group)
        $filesWithStatusOne = $group->files()->where('status', '=', 0)->exists();

        if ($filesWithStatusOne) {
            return response()->json(['message' => 'Cannot delete group with associated files'], 422);
        }

        // Delete the group and associated group members (groupMembers will be deleted via the deleting event)
        $group->delete();

        return response()->json(['message' => 'Group , associated members and the files in this group is deleted successfully']);
    }

    //------------------------------------------------------------------------------------------------------------------------

    public function allGroups()
    {
        //get the authenticated user
        $user =  Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 400);
        }

        // Retrieve all groups that contain the authenticated user as a member

        $groups = Group_member::query()
            ->where('user_id', '=', $user->id)
            ->join('groups','groups.id','=','group_members.group_id')
            ->get('groups.*');

        // Transform the result to include name, admin_id, and member_count
        $formattedGroups = $groups->map(function ($group) {
            $num=Group_member::where('group_id','=',$group->id)->count();
            return [
                'msg'=>'hi',
                'group_id' => $group->id,
                'name' => $group->name,
                'admin_id' => $group->admin_id,
                'member_count' => $num,
            ];
        });

        return response()->json(['message' => 'All groups:', 'groups' => $formattedGroups], 200);
    }


    //------------------------------------------------------------------------------------------------------------------------

    // users for specific group
    public function usersGroup($id)
    {
        $user =  Auth::user();//get the authenticated user
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 400);
        }
        $group = Group::query()->find($id);// Retrieve the group
        if (!$group) {
            return response()->json(['message' => 'Invalid Group ID'], 404);
        }
        $group_member = Group_member::where('group_members.group_id', '=', $id)
            ->where('group_members.user_id', '=', $user->id)->exists();
        if (!$group_member) {//check if the authenticated user is a member in the group
            return response()->json(['message' => 'you are not a member in this group'], 400);
        }
        // Retrieve the admin's username and email
        $admin = User::query()->find($group->admin_id);
        $adminUsername = $admin ? $admin->username : null;
        $adminEmail = $admin ? $admin->email : null;
        // Retrieve the usernames and emails of users in the group
        $groupMembers = Group_member::query()->where('group_id', $id)
            ->with(['user:id,username,email'])->get(['user_id']);
        $userDetails = $groupMembers->map(function ($groupMember) {
            return [
                'member_id' => $groupMember->user->id,
                'username' => $groupMember->user->username,
                'email' => $groupMember->user->email,
            ];
        });
        return response()->json([
            'message' => 'Users in this group',
            'group_name' => $group->name,
            'admin_username' => $adminUsername,
            'admin_email' => $adminEmail,
            'group_members' => $userDetails,
        ], 200);
    }

    //------------------------------------------------------------------------------------------------------------------------

    //display View user groups
    public function viewUserGroup($id)
    {
        // Retrieve the groups associated with the user based on the provided ID
        $userGroups = Group_member::where('user_id', $id)->with('group')->get();

        if ($userGroups->isEmpty()) {
            return response()->json(['message' => 'User not found or not associated with any group'], 404);
        }

        $groupData = $userGroups->map(function ($userGroup) {
            $group = $userGroup->group;
            $memberCount = $group->group_member->count(); // Count the members for each group

            return [
                'name' => $group->name,
                'member_count' => $memberCount,
            ];
        });

        return response()->json([
            'message' => 'Groups associated with the user',
            'userGroups' => $groupData,
        ], 200);
    }

    //------------------------------------------------------------------------------------------------------------------------


    public function deleteMember($group_id, $user_id)
    {
        // Check if the user is authenticated
        $authenticatedUser = Auth::user();
        if (!$authenticatedUser) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        // Find the group member based on the provided group_id and user_id
        $groupMember = Group_member::where('group_id', $group_id)
            ->where('user_id', $user_id)
            ->first();
        if (!$groupMember) {
            return response()->json(['message' => 'Group member not found in the specified group'], 404);
        }

        // Check if the authenticated user is the group admin who created the group
        if ($authenticatedUser->id !== $groupMember->group->admin_id) {
            return response()->json(['message' => 'Unauthorized. You are not the group admin who created the group.'], 401);
        }
        // Check if the group member has booked any files
        $bookedFilesExist = File::query()->where('booker_id','=', $groupMember->user_id)->exists();

        if ($bookedFilesExist) {
            return response()->json(['message' => 'Sorry. You cannot delete this member because they have booked a file.'], 401);
        }
        $groupMember->delete();
        return response()->json(['message' => 'The member deleted successfully']);
    }

    //------------------------------------------------------------------------------------------------------------------------

}