<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Funnel;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

final class ScaleController extends Controller
{
    public function __invoke(): View
    {
        return view('web.get-started.scale');
    }
}
