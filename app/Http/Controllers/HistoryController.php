<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\History;
use DateTime;
use Illuminate\Http\Request;

class HistoryController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }
    
    public function store($group_id, $file_id, $user_id, $event)
    {
        $file = File::where('id', $file_id);
        $dt = new DateTime();

        $history = new History();
        $history->user_id = $user_id;
        $history->group_id = $group_id;
        $history->file_id = $file_id;
        $history->event = $event . " " . $file->name . ' in ' . $dt->format('d-m-Y');
        $history->save();
        echo $history->event;
    }
    public function fileHistory($group_id, $file_id)
    {
        // $user=$request->user();
        $history = History::join('users', 'users.id', '=', 'histories.user_id')
            ->where('histories.group_id', '=', $group_id)
            ->where('histories.file_id', '=', $file_id)
            ->select('users.name as user_name', 'histories.*')
            ->get();

        return response(['message' => 'done', 'data' => $history], 200);
    }
    public function userHistory($group_id, $user_id)
    {

        $history = History::join('users', 'users.id', '=', 'histories.user_id')
            ->where('hstories.group_id', '=', $group_id)
            ->where('histories.user_id', '=', $user_id)
            ->select('users.name as user_name', 'histories.*')
            ->get();

        return $history;
    }
}
