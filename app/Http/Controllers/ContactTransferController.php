<?php

namespace App\Http\Controllers;

use App\Models\{Contact, User};
use App\Services\ContactTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Validation\ValidationException;

class ContactTransferController extends Controller
{
    public function edit(Contact $contact, ContactTransfer $transfer)
    {
        $snapshot = $transfer->preview($contact);
        $preview = collect($snapshot)->except(['contact_id', 'owner_id'])->map(fn ($ids) => count($ids))->all();
        $previewToken = Crypt::encryptString(json_encode(['actor_id' => auth()->id(), 'snapshot' => $snapshot]));
        $staff = User::where('role', 'cs')->where('active', true)->whereKeyNot($contact->owner_id)->orderBy('name')->get();
        return view('contacts.transfer', compact('contact', 'preview', 'previewToken', 'staff'));
    }

    public function update(Request $request, Contact $contact, ContactTransfer $transfer)
    {
        $data = $request->validate([
            'owner_id' => ['required', 'integer', 'exists:users,id'],
            'preview_token' => ['required', 'string'],
            'confirm_transfer' => ['accepted'],
        ]);
        try {
            $payload = json_decode(Crypt::decryptString($data['preview_token']), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException | \JsonException $exception) {
            throw ValidationException::withMessages(['preview' => 'Pratinjau tidak valid. Muat ulang halaman pengalihan.']);
        }
        if (! is_array($payload) || ($payload['actor_id'] ?? null) !== $request->user()->id
            || ($payload['snapshot']['contact_id'] ?? null) !== $contact->id) {
            throw ValidationException::withMessages(['preview' => 'Pratinjau tidak valid. Muat ulang halaman pengalihan.']);
        }
        $transfer->transfer($contact, User::findOrFail($data['owner_id']), $payload['snapshot']);
        return redirect()->route('contacts.show', $contact)->with('success', 'Kontak dan pekerjaan terkait berhasil dialihkan.');
    }
}
