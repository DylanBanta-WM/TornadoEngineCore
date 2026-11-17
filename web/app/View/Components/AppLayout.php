<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    /**
     * @param  bool  $fill  The page fills the space between the menu and the footer exactly,
     *                      and scrolls inside itself (like Chat).
     */
    public function __construct(public bool $fill = false) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.app');
    }
}
