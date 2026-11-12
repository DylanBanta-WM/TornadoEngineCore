<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class SetupLayout extends Component
{
    public function __construct(public bool $wide = false) {}

    public function render(): View
    {
        return view('layouts.setup');
    }
}
