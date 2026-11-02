<?php

namespace App\Http\Controllers;

use App\Http\Requests\HandlingRequest;
use App\Models\{Inquiry, WhatsappBotConversation};
use App\Services\HandlingWorkflow;

class HandlingController extends Controller
{
    public function inquiry(HandlingRequest $request, Inquiry $inquiry, HandlingWorkflow $workflow)
    {
        $workflow->save($inquiry, $request->user(), $request->validated());
        return back()->with('success', 'Hasil penanganan tersimpan.');
    }
    public function conversation(HandlingRequest $request, WhatsappBotConversation $conversation, HandlingWorkflow $workflow)
    {
        $workflow->save($conversation, $request->user(), $request->validated());
        return back()->with('success', 'Hasil penanganan tersimpan.');
    }
}
