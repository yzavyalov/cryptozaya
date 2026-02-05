<?php

namespace App\Http\Controllers;

use App\Services\EncodeService;
use Illuminate\Http\Request;

class ShowKeysController extends Controller
{
    public function form()
    {
        return view('key');
    }

    public function show(Request $request)
    {
        $key = $request->only('key');

        $string = EncodeService::encrypte($key['key']);

        $stringAfter = EncodeService::decrypte($string);

        if ($key['key'] === $stringAfter)
            $result = true;
        else
            $result = false;

        dd($key['key'],$string,$stringAfter,$result);

    }
}
