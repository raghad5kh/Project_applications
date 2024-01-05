<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Group_member;
use App\Models\Group_file;
use App\Models\File;
use App\Services\UserService;

class GroupService extends Service
{
    public function __construct(private UserService $userService){}

    public function store($groupName, $adminId)
    {
       // Create the group with admin_id set to the user's ID
       $group = Group::query()->create([
            'name' => $groupName,
            'admin_id' => $adminId,
        ]);

        $group->group_member()->create([
            'user_id' => $adminId,
        ]);
        
        return ['message' => 'Group created successfully', 'group' => $group];
    }

    public function getGroupById($groupId) 
    {
        return Group::query()->where('id', $groupId)->first();
    }

    public function isMemberExist($groupId, $memberId)
    {
        return Group_member::where('group_members.group_id', '=', $groupId)
            ->where('group_members.user_id', '=', $memberId)
            ->exists();
    }

    public function isGroupHasBookedFile($group)
    {
        return $group->files()->where('status', '=', 0)->exists();
    }

    public function deleteGroup($authenticatedUserId, $groupId)
    {
        $group = $this->getGroupById($groupId);
        if (!$group) {// Check if the group exists
            return ['message' => 'Group not found', 'status' => 404];
        }
        // Check if the authenticated user owns the group
        if ($authenticatedUserId !== $group->admin_id) {
            return ['message' => 'You are not an admin of this group', 'status' => 401];
        }

        $filesWithStatusOne = $this->isGroupHasBookedFile($group);
        if ($filesWithStatusOne) {
            return ['message' => 'Cannot delete group with associated files', 'status' => 422];
        }

        $group->delete();
        return ['message' => 'Group , associated members and the files in this group is deleted successfully'];
    }

    public function addMember($authenticatedUserId, $groupId, $userToAdd)
    { 
        $group = $this->getGroupById($groupId);
        $user = $this->userService->getUserByEmailOrUsername($userToAdd);
        if (!$group || !$user) { // Check if the group and user exist
            return ['message' => 'Group or user not found', 'status' => 404];
        }

        // Check if the authenticated user is the group admin who created the group
        if ($authenticatedUserId !== $group->admin_id) {
            return ['message' => 'Unauthorized. You are not the group admin who created the group.', 'status' => 401];
        }

        $isMemberExist = $this->isMemberExist($group->id, $user->id);
        if ($isMemberExist) {//check if the user want to add is already exist in the group
            return ['message' => 'this user is already exist in this group', 'status' => 400];
        }
        $member = $group->group_member()->create(['user_id' => $user->id]);
        return ['message' => 'Group member added successfully', 'member' => $member];
    }
    
    public function getUserGroups($userId)
    { 
        $groups = Group_member::query()
            ->where('user_id', '=', $userId)
            ->join('groups','groups.id','=','group_members.group_id')
            ->get('groups.*');

        $formattedGroups = $groups->map(function ($group) {
            $num=Group_member::where('group_id','=',$group->id)->count();
            return [
                'group_id' => $group->id,
                'name' => $group->name,
                'admin_id' => $group->admin_id,
                'member_count' => $num,
            ];
        });

        return ['message' => 'All groups', 'allGroups' => $formattedGroups];
    }


    public function getGroupAdmin($groupAdminId)
    {
        $admin = $this->userService->getUserById($groupAdminId);
        $adminUsername = $admin ? $admin->username : null;
        $adminEmail = $admin ? $admin->email : null;
        return [
            'adminUsername' => $adminUsername,
            'adminEmail' => $adminEmail,
        ];
    }

    public function getGroupMembers($authenticatedUserId, $groupId)
    {
        $group = $this->getGroupById($groupId);
        if (!$group) {// Check if the group exists
            return ['message' => 'Group not found', 'status' => 404];
        }

        $isMemberExist = $this->isMemberExist($group->id, $authenticatedUserId);
        if (!$isMemberExist) {//check if the authenticated user is a member in the group
            return ['message' => 'you are not a member in this group', 'status' => 400];
        }
        $admin = $this->getGroupAdmin($group->admin_id);
        $groupMembers = Group_member::query()->where('group_id', $groupId)
            ->with(['user:id,username,email'])->get(['user_id']);
        $userDetails = $groupMembers->map(function ($groupMember) {
            return [
                'member_id' => $groupMember->user->id,
                'username' => $groupMember->user->username,
                'email' => $groupMember->user->email,
            ];
        });

        return ['message' => 'Users in this group', 'group' => $group, 'userDetails' => $userDetails, 'admin' => $admin];
    }

    public function isMemberHasBookedFiles($group_id, $memberId)
    {
        return Group_file::join('files', 'group_files.file_id', '=', 'files.id')
            ->leftJoin('users', 'users.id', '=', 'files.booker_id')
            ->where('group_files.group_id', '=', $group_id)
            ->where('files.booker_id', '=', $memberId)
            ->exists();
    }

    public function deleteMember($authenticatedUserId, $groupId, $memberId)
    {
        $group = $this->getGroupById($groupId);
        $isMemberExist = $this->isMemberExist($groupId, $memberId);
        if (!$isMemberExist) {
            return ['message' => 'Group member not found in the specified group', 'status' => 404];
        }
        if ($authenticatedUserId !== $group->admin_id) {
            return ['message' => 'Unauthorized. You are not the group admin who created the group.', 'status' => 401];
        }
        $bookedFilesExist = $this->isMemberHasBookedFiles($groupId, $memberId);
        if ($bookedFilesExist) {
            return ['message' => 'Sorry. You cannot delete this member because they have booked a file.', 'status' => 401];
        }
        $groupMember = Group_member::where('group_id', $groupId)
            ->where('user_id', $memberId)
            ->first();
        $groupMember->delete();
        return ['message' => 'The member deleted successfully'];
    }
}