<?php

namespace App\Support;

use App\Models\EstateAgentProspect;
use App\Models\User;

class ProspectTemplateRenderer
{
    public static function render(string $text, EstateAgentProspect $prospect, ?User $user = null): string
    {
        $recipient = $prospect->selected_email
            ?: ($prospect->best_emails[0] ?? null)
            ?: ($prospect->emails_found[0] ?? null);

        $replacements = [
            '{{agency_name}}' => $prospect->agency_name,
            '{{town}}' => $prospect->town,
            '{{website}}' => $prospect->website ?? '',
            '{{contact_page_url}}' => $prospect->contact_page_url ?? '',
            '{{selected_email}}' => $prospect->selected_email ?? '',
            '{{recipient_email}}' => $recipient ?? '',
            '{{email}}' => $recipient ?? '',
            '{{sender_name}}' => $user?->name ?? '',
            '{{sender_email}}' => $user?->email ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }
}
