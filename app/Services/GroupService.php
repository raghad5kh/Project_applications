<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Group_member;
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
        
        return $group;
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

    public function deleteGroup($group)
    {
        return $group->delete();
    }

    public function addMember($group, $userToAddId)
    { 
        return $group->group_member()->create(['user_id' => $userToAddId]);
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
                'msg'=>'hi',
                'group_id' => $group->id,
                'name' => $group->name,
                'admin_id' => $group->admin_id,
                'member_count' => $num,
            ];
        });

        return $formattedGroups;
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

    public function getGroupMembers($groupId)
    {
        $groupMembers = Group_member::query()->where('group_id', $groupId)
            ->with(['user:id,username,email'])->get(['user_id']);
        $userDetails = $groupMembers->map(function ($groupMember) {
            return [
                'member_id' => $groupMember->user->id,
                'username' => $groupMember->user->username,
                'email' => $groupMember->user->email,
            ];
        });

        return $userDetails;
    }
}