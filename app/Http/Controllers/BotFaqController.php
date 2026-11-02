<?php

namespace App\Http\Controllers;

use App\Models\BotFaq;
use Illuminate\Http\Request;

class BotFaqController extends Controller
{
    public function index()
    {
        return view('admin.faqs', ['faqs' => BotFaq::orderBy('id')->get()]);
    }

    public function save(Request $request, ?BotFaq $faq = null)
    {
        $data = $request->validate(['question' => ['required', 'string', 'max:255'], 'answer' => ['required', 'string', 'max:3000'], 'active' => ['required', 'boolean']]);
        if ($faq) $faq->update($data);
        else BotFaq::create($data);
        return redirect()->route('admin.faqs')->with('success', 'Informasi bot disimpan.');
    }
}
