<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\UnderstandGpsr;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

final class UnderstandGpsrController extends Controller
{
    public function __invoke(): View
    {
        return view('web.understand-gpsr.index');
    }
}
