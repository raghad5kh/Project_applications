<?php

namespace App\Http\Controllers;

use App\Http\Resources\AddressResource;
use App\Models\Address;
use App\Models\User;
use App\Traits\ApiResponder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AddressController extends Controller
{
    //
    use ApiResponder;

    public function __construct()
    {
        $this->middleware('auth:api')->except('show');
    }

    public function set(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'title' => 'required|string',
            'area' => 'required|string',
            'street' => 'required|string',
            'floor' => 'required|numeric',
            'near' => 'required|string',
            'details' => 'string'
        ]);
        if ($validator->fails()) {
            return $this->badRequestResponse(null, $validator->errors()->toJson());
        }

        $address = new Address();
        $address->user_id = Auth::id();
        $address->title = $request->title;
        $address->area = $request->area;
        $address->street = $request->street;
        $address->floor = $request->floor;
        $address->near = $request->near;
        $address->details = $request->details;
        $address->save();

        $user = User::findOrFail(Auth::id());
        $user->update([
            $user->step = 4
        ]);//address

        return $this->okResponse(null, 'your address added successfully');
    }

    public function update(Request $request, Address $address)
    {

        $validator = Validator::make($request->all(), [
            'title' => 'string',
            'area' => 'string',
            'street' => 'string',
            'floor' => 'numeric',
            'near' => 'string',
            'details' => 'string'
        ]);
        if ($validator->fails()) {
            return $this->badRequestResponse(null, $validator->errors()->toJson());
        }

        if ($request->has('title')) {
            $address->title = $request->title;
        }

        if ($request->has('area')) {
            $address->area = $request->area;
        }

        if ($request->has('street')) {
            $address->street = $request->street;
        }

        if ($request->has('floor')) {
            $address->floor = $request->floor;
        }

        if ($request->has('near')) {
            $address->near = $request->near;
        }

        if ($request->has('details')) {
            $address->details = $request->details;
        }

        if (!$address->isDirty()) {
            return $this->errorResponse(null, 'You need to specify a different value to update', 422);
        }

        $address->save();

        return $this->okResponse(null, 'your address updated successfully');
    }

    public function indexForUser(){
        return $this->okResponse(
            AddressResource::collection(Address::where('user_id',Auth::id())->get()),
            'user addresses'
        );
    }
}
