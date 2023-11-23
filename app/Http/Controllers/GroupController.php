<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Group_member;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GroupController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
        ]);

        $user = auth()->user(); // Get the authenticated user

        // Create the group with fk_admin_id set to the user's ID
        $group = Group::query()->create([
            'name' => $data['name'],
            'admin_id' => $user->id,
        ]);

        return response()->json(['message' => 'Group created successfully', 'group' => $group]);
    }

//------------------------------------------------------------------------------------------------------------------------

    public function groupMember(string $name_group, string $user)
    {
        // Check if the user is authenticated
        $authenticatedUser = Auth::user();
        if (!$authenticatedUser) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Retrieve the group and user based on their names
        $group = Group::where('name', '=', $name_group)->first();
        $userToAdd = User::where('email', $user)->orWhere('username', $user)->first();

        // Check if the group and user exist
        if (!$group || !$userToAdd) {
            return response()->json(['message' => 'Group or user not found'], 404);
        }

        // Check if the authenticated user is the group admin who created the group
        if ($authenticatedUser->id !== $group->admin_id) {
            return response()->json(['message' => 'Unauthorized. You are not the group admin who created the group.'], 401);
        }

        // Check if the authenticated user is trying to add themselves to the group
        if ($authenticatedUser->id === $userToAdd->id) {
            return response()->json(['message' => 'You cannot add yourself to the group'], 400);
        }

        // Create a new GroupMember using the relationships (pluralized method)
        $groupMember = $group->group_member()->create([
            'user_id' => $userToAdd->id,
        ]);

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

        // Check if there are associated files with status '1' for the group
        $filesWithStatusOne = $group->files()->where('status', '=', 1)->exists();

        if ($filesWithStatusOne) {
            return response()->json(['message' => 'Cannot delete group with associated files'], 422);
        }

        // Delete the group and associated group members (groupMembers will be deleted via the deleting event)
        $group->delete();

        return response()->json(['message' => 'Group and associated members deleted successfully']);
    }

//------------------------------------------------------------------------------------------------------------------------

    public function allGroups()
    {
        $groups = Group::query()
            ->select('name')
            ->get();
        return response()->json(['message' => 'All groups:', 'groups' => $groups], 200);
    }

//------------------------------------------------------------------------------------------------------------------------

    // users for specific group
    public function usersGroup($id)
    {
        $group = Group::query()->find($id);

        if (!$group) {
            return response()->json(['message' => 'Invalid Group ID'], 404);
        }

        // Retrieve the admin's username
        $adminUsername = User::query()->where('id', $group->admin_id)->value('username');

        // Retrieve the usernames of users in the group
        $usernames = Group_member::query()->where('group_id', $id)
            ->with(['user:id,username'])
            ->get(['user_id'])
            ->pluck('user.username');

        return response()->json(['message' => 'Users in this group', 'admin_username' => $adminUsername, 'group member' => $usernames], 200);
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

        $groupNames = $userGroups->pluck('group.name')->toArray();

        return response()->json([
            'message' => 'Groups associated with the user',
            'userGroups' => $groupNames,
        ], 200);
    }

}
