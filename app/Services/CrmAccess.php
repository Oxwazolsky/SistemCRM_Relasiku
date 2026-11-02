<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class CrmAccess
{
    public function role(User $user, string ...$roles): bool
    {
        return (bool) $user->active && in_array($user->role, $roles, true);
    }

    public function canRead(User $user, Model $record): bool
    {
        if (! $user->active) return false;
        if ($user->role === 'owner' || $user->role === 'admin') return true;
        if ($user->role !== 'cs') return false;
        return $this->owns($user, $record);
    }

    public function canWrite(User $user, Model $record): bool
    {
        if (! $this->role($user, 'owner', 'cs')) return false;
        return $user->role === 'owner' || $this->owns($user, $record);
    }

    public function owns(User $user, Model $record): bool
    {
        if ($record instanceof \App\Models\Inquiry) {
            if ($record->contact_id) {
                return (int) $record->assigned_to === (int) $user->id
                    && \App\Models\Contact::whereKey($record->contact_id)->where('owner_id', $user->id)->exists();
            }

            return $record->assigned_to === null || (int) $record->assigned_to === (int) $user->id;
        }
        if ($record instanceof \App\Models\Ticket && $record->assigned_to === null && $record->contact_id === null) {
            return $record->inquiry?->intent === 'complaint'
                && $record->inquiry?->source === 'whatsapp';
        }
        if ($record instanceof \App\Models\WhatsappBotConversation && $record->handed_off) {
            return $record->assigned_to === null || (int) $record->assigned_to === (int) $user->id;
        }
        if ($record instanceof \App\Models\Order) return \App\Models\Contact::whereKey($record->contact_id)->where('owner_id', $user->id)->exists();
        if ($record instanceof \App\Models\Opportunity) return \App\Models\Contact::whereKey($record->contact_id)->where('owner_id', $user->id)->exists();
        if ($record->getAttribute('owner_id') !== null) return (int) $record->owner_id === (int) $user->id;
        if ($record->getAttribute('assigned_to') !== null) return (int) $record->assigned_to === (int) $user->id;
        if ($record->getAttribute('contact_id') !== null) {
            return \App\Models\Contact::whereKey($record->contact_id)->where('owner_id', $user->id)->exists();
        }
        if ($record->getAttribute('opportunity_id') !== null) {
            return \App\Models\Opportunity::whereKey($record->opportunity_id)->where('owner_id', $user->id)->exists();
        }
        return false;
    }

    public function scope(Builder $query, User $user, string $type): Builder
    {
        abort_unless($user->active && in_array($user->role, ['admin', 'owner', 'cs'], true), 403);
        if ($user->role !== 'cs') return $query;
        return match ($type) {
            'contacts' => $query->where('owner_id', $user->id),
            'opportunities' => $query->whereHas('contact', fn (Builder $contact) => $contact->where('owner_id', $user->id)),
            'tasks' => $query->where('assigned_to', $user->id),
            'tickets' => $query->where(fn (Builder $q) => $q->where('assigned_to', $user->id)->orWhere(fn (Builder $unassigned) => $unassigned->whereNull('assigned_to')->whereNull('contact_id')->whereHas('inquiry', fn (Builder $inquiry) => $inquiry->where('intent', 'complaint')->where('source', 'whatsapp')))), 
            'inquiries' => $query->where(fn (Builder $q) => $q->where('assigned_to', $user->id)->where(fn (Builder $assigned) => $assigned->whereNull('contact_id')->orWhereHas('contact', fn (Builder $contact) => $contact->where('owner_id', $user->id)))->orWhere(fn (Builder $unassigned) => $unassigned->whereNull('assigned_to')->whereNull('contact_id'))),
            'interactions' => $query->whereHas('contact', fn (Builder $q) => $q->where('owner_id', $user->id)),
            'orders' => $query->whereHas('contact', fn (Builder $contact) => $contact->where('owner_id', $user->id)),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public function contact(User $user, int $id): void
    {
        abort_unless($this->canRead($user, \App\Models\Contact::findOrFail($id)), 403);
    }

    public function opportunity(User $user, int $id): void
    {
        abort_unless($this->canRead($user, \App\Models\Opportunity::findOrFail($id)), 403);
    }
}
