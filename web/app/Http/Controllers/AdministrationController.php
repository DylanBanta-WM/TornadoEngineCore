<?php

namespace App\Http\Controllers;

use App\Administration\Administration;
use Illuminate\View\View;

class AdministrationController extends Controller
{
    public function index(Administration $administration): View
    {
        return view('administration.index', ['sections' => $administration->sections()]);
    }
}
