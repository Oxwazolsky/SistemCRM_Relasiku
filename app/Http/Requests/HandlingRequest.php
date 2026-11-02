<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HandlingRequest extends FormRequest
{
    public function authorize(): bool { return app(\App\Services\CrmAccess::class)->role($this->user(), 'owner', 'cs'); }
    public function rules(): array
    {
        return ['action' => ['required', Rule::in(['note', 'waiting', 'resolved', 'cancelled'])],
            'body' => ['required', 'string', 'max:5000'], 'submission_id' => ['required', 'uuid']];
    }
}
