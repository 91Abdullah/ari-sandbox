<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\CallRequest;
use App\Events\OriginateEvent;

class CallController extends Controller
{
    public function originate(Request $request)
    {
        $req = new CallRequest();
        $req->endpointA = $request->endpointA;
        $req->endpointB = $request->endpointB;
        $req->save();
        event(new OriginateEvent($req));
        return response()->json('Call has been generated', 200);
    }
}
