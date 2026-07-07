<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Contact;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

final class ContactController extends Controller
{
    public function __invoke(): View
    {
        return view('web.contact.index');
    }
}
