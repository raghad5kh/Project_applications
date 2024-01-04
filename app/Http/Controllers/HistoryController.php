<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\History;
use DateTime;
use Error;
use Exception;
use GuzzleHttp\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\CoveredCodeNotExecutedException;

class HistoryController extends Controller
{

    public function __construct()
    {
        $this->middleware(['transactional', 'auth:sanctum']);
        // $this->middleware();
    }

    public function store($group_id, $file_id, $user_id, $event, $proved)
    {
        DB::beginTransaction();
        $dt = new DateTime();
        try {
            $history = new History();
            $history->user_id = $user_id;
            $history->group_id = $group_id;
            $history->file_id = $file_id;
            $history->event = $event;
            $history->proved = $proved;
            $history->time = $dt->format('d-m-Y');
            $history->save();
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    public function fileHistory($group_id, $file_id)
    {
        //get the authenticated user
        $user =  Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 400);
        }

        $history = History::join('users', 'users.id', '=', 'histories.user_id')
            ->join('files', 'files.id', '=', 'histories.file_id')
            ->where('histories.group_id', '=', $group_id)
            ->where('histories.file_id', '=', $file_id)
            ->where(function ($query) {
                $query->where('histories.proved', '=', 1)
                    ->orwhere('histories.event', '=', 'Reserve');
            })
            ->select('histories.id', 'histories.event','histories.time', 'users.name as user_name', 'files.name as file_name')
            ->get();
        return response(['message' => 'done', 'data' => $history], 200);
    }

    public function userHistory($group_id, $user_id)
    {
         //get the authenticated user
         $user =  Auth::user();
         if (!$user) {
             return response()->json(['message' => 'Unauthorized'], 400);
         }

        $history = History::join('users', 'users.id', '=', 'histories.user_id')
            ->join('files', 'files.id', '=', 'histories.file_id')
            ->where('histories.group_id', '=', $group_id)
            ->where('histories.user_id', '=', $user_id)
            ->where(function ($query) {
                $query->where('histories.proved', '=', 1)
                    ->orwhere('histories.event', '=', 'Reserve');
            })
            ->select('users.name as user_name', 'files.name as file_name', 'histories.id', 'histories.event','histories.time')
            ->get();

        return response(['message' => 'done', 'data' => $history], 200);
    }
}
