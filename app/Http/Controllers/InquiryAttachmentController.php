<?php

namespace App\Http\Controllers;

use App\Models\InquiryAttachment;
use App\Services\CrmAccess;
use Illuminate\Support\Facades\Storage;

class InquiryAttachmentController extends Controller
{
    public function show(InquiryAttachment $attachment, CrmAccess $access)
    {
        abort_unless($attachment->inquiry && $access->canRead(auth()->user(), $attachment->inquiry), 404);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);
        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name ?: 'bukti-keluhan');
    }
}
