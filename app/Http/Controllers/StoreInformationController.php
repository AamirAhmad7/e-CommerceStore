<?php

namespace App\Http\Controllers;

use App\Services\CommerceSettings;
use App\Support\StoreSettings;
use Illuminate\View\View;

class StoreInformationController extends Controller
{
    public function show(string $page): View
    {
        $titles = ['about' => 'About us', 'contact' => 'Contact us', 'faq' => 'Help & FAQs', 'privacy' => 'Privacy notice', 'terms' => 'Ordering terms'];
        abort_unless(isset($titles[$page]), 404);

        return view('shop.information', ['page' => $page, 'title' => $titles[$page], 'canonical' => route('information.'.$page), 'pages' => StoreSettings::pages(), 'settings' => CommerceSettings::get(), 'whatsapp' => StoreSettings::whatsappNumber()]);
    }
}
