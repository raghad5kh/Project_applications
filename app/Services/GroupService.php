<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Group_member;

class GroupService extends Service
{
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

    public function groupMember($group, $userToAddId)
    { 
        return $group->group_member()->create(['user_id' => $userToAddId]);
    }
}