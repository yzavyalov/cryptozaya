<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PageController extends Controller
{
    public function index()
    {
        return view('index');
    }

    public function cabinet()
    {
        return view('cabinet.index');
    }

    public function newUser()
    {
        return view('cabinet.new-user-form');
    }

    public function documentation()
    {
        return view('cabinet.documentation');
    }

}
