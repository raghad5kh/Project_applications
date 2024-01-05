<?php



namespace App\Services;


use App\Http\Controllers\HistoryController;
use App\Models\File;
use App\Models\Group_file;
use App\Models\Group_member;
use App\Services\GroupService;

class GroupFileService extends Service
{
    public function __construct(private GroupService $groupService){}

    public function getFileById($file_id) 
    {
        return File::where('id', $file_id)->first();
    }

    public function isFileBooked(File $file) 
    {
        if ($file->status == false) {
            return true;
        }
        return false;
    }

    public function isFileExistInGroup($group_id, $file_id)
    {
        return Group_file::where('group_id', '=', $group_id)
            ->where('file_id', '=', $file_id)->exists();
    }

    public function canUserReadFile($userId, $file_id)
    {
        return Group_member::join('group_files', 'group_files.group_id', '=', 'group_members.group_id')
            ->where('group_members.user_id', '=', $userId)->where('group_files.file_id', '=', $file_id);
    }

    public function read($userId, $group_id, $file_id)
    {
        $file = $this->getFileById($file_id);
        if (!$file) {
            return ['message' => "the file is not exist!", 'status' => 400];
        }
        if ($this->isFileBooked($file)) {
            return ['message' => "the file is booked!", 'status' => 400];
        }
        $exist = $this->isFileExistInGroup($group_id, $file_id);
        if (!$exist) {
            return ['message' => "the file is not exist in this group !", 'status' => 400];
        }
        $check = $this->canUserReadFile($userId, $file_id);
        if (!$check) {
            return ['message' => "this file isn't available", 'status' => 400];
        }
        if (!file_exists($file->path)) {// Check if the file exists
            return ['message' => "file is not found", 'status' => 400];
        }
        $file_content = file_get_contents($file->path, true);
        $file_name = basename($file->path);
        //store in history
        (new HistoryController())->store($group_id, $file_id, $userId, 'read', true);
        return [
            'file_name' => $file_name,
            'file_content' => $file_content
        ];
    }

    public function showGroupFilesToAdding($group_id, $member_id)
    {
        $group_member = $this->groupService->isMemberExist($group_id, $member_id);
        if (!$group_member) {
            return ['message' => 'you are not a member in this group', 'status' => 400];
        }

        $files = File::leftJoin('group_files', 'files.id', '=', 'group_files.file_id')
            ->leftJoin('groups', 'group_files.group_id', '=', 'groups.id')
            ->where('files.user_id', '=', $member_id)
            ->whereNull('groups.id')
            ->orWhere('groups.id', '<>', $group_id)
            ->select('files.id', 'files.name')
            ->get();
        return [
            'data' => $files
        ];
    }

    public function addToGroup($group_id, $member_id, $file_ids)
    {
        // if user in group or not
        $group_member = $this->groupService->isMemberExist($group_id, $member_id);
        if (!$group_member) {
            return ['message' => 'you are not a member in this group', 'status' => 400];
        }

        foreach ($file_ids as $id) {
            $group_file = $this->isFileExistInGroup($group_id, $id);
            if ($group_file) {
                return ['message' => "the file is already exist in this group.", 'status' => 400];
            }
            $group_file = new Group_file();
            $group_file->group_id = $group_id;
            $group_file->file_id = $id;
            $group_file->save();
        }
        foreach ($file_ids as $id) {
            (new HistoryController)->store($group_id, $id, $member_id, 'add', true);
        }
        return ['message' => "your file is added!"];
    }


    public function showGroupFiles($group_id, $member_id)
    {
        $group = $this->groupService->getGroupById($group_id);
        if (!$group) {
            return ['message' => " the group is not exist", 'status' => 400];
        }

        $group_member = $this->groupService->isMemberExist($group_id, $member_id);
        if (!$group_member) {
            return ['message' => 'you are not a member in this group', 'status' => 400];
        }

        $files = Group_file::join('files', 'group_files.file_id', '=', 'files.id')
            ->leftJoin('users', 'users.id', '=', 'files.booker_id')
            ->select('files.id', 'files.name', 'files.status', 'group_files.created_at', 'users.name as booker_name')
            ->where('group_files.group_id', '=', $group_id)
            ->get();

        return ['data' => $files];
    }

    public function showunBookedFiles($group_id)
    {
        $group = $this->groupService->getGroupById($group_id);
        if (!$group) {
            return ['message' => " the group is not exist", 'status' => 400];
        }

        $group_member = $this->groupService->isMemberExist($group_id, $member_id);
        if (!$group_member) {
            return ['message' => 'you are not a member in this group', 'status' => 400];
        }

        $files = Group_file::join('files', 'group_files.file_id', '=', 'files.id')
            ->where('files.status', '=', '1')
            ->where('group_files.group_id', '=', $group_id)
            ->get('files.*');

        return [
            'message' => "done!",
            'data' => $files
        ];
    }

    public function removeFromGroup($user_id, $group_id, $file_id)
    {
        $group = $this->groupService->getGroupById($group_id);
        $file = $this->getFileById($file_id);

        if (!$group) {
            return ['message' => "group is not exist", 'status' => 400];
        } else if (!($user_id == $file->user_id)) {
            return ['message' => "you can't remove this file because you are not the file owner", 'status' => 400];
        } else if ($this->isFileBooked($file)) {
            return ['message' => "the file is booked!", 'status' => 400];
        }

        Group_file::where('group_id', $group_id)
            ->where('file_id', $file_id)
            ->delete();

        (new HistoryController)->store($group_id, $file_id, $user_id, 'delete', true);

        return ['message' => "deleting is done!"];
    }

}

