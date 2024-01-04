<?php

namespace App\Services;

use App\Models\File;
use App\Models\User;
use App\Models\Group;

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
}