<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\ExcludedProducts;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

final class ExcludedProductsController extends Controller
{
    public function __invoke(): View
    {
        return view('web.excluded-products.index');
    }
}
