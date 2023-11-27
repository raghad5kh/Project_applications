<?php

namespace App\Aspects;

use AhmadVoid\SimpleAOP\Aspect;
use App\Models\History;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class FileAspect implements Aspect
{

    // The constructor can accept parameters for the attribute
    public function __construct()
    {

    }

    public function executeBefore($request, $controller, $method)
    {
        DB::beginTransaction();
    }

    public function executeAfter($request, $controller, $method, $response)
    {
        echo $request;
        DB::commit();
        $history=new History();
        $history->user_id=$request()->user()->id;

        
    }

    public function executeException($request, $controller, $method, $exception)
    {
        dB::rollBack();
        Log::error($exception->getMessage());
        // return response()->json(['message'=>'something went wrong, please try again.']);
    }
   
}
