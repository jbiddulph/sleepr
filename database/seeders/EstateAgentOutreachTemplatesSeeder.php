<?php

namespace Database\Seeders;

use App\Models\EstateAgentOutreachTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EstateAgentOutreachTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'ZapTask Property Management',
                'subject' => 'Quick idea for {{agency_name}} in {{town}}',
                'body' => <<<'TEXT'
Hi there,

I came across {{agency_name}} in {{town}} and wanted to reach out.

I'm {{sender_name}}, founder of ZapTask (www.zaptask.co.uk) — a UK platform that connects homeowners, landlords and tenants with trusted local tradespeople and service providers.

As a property management company, you regularly need reliable trades for repairs, maintenance, cleaning, and emergency call-outs across your portfolio. ZapTask gives you a simple way to find vetted local professionals quickly — whether it's for a one-off job or ongoing work across multiple properties.

It can also help reduce the time your team spends sourcing contractors and answering tenant requests for local services.

If it sounds useful, I'd be happy to arrange a quick 10-minute call or answer any questions by email.

You can find out more at www.zaptask.co.uk.

Best regards,
{{sender_name}}
Founder, ZapTask
www.zaptask.co.uk
TEXT,
            ],
            [
                'name' => 'ZapTask Construction',
                'subject' => 'More local work for {{agency_name}} in {{town}}?',
                'body' => <<<'TEXT'
Hi there,

I came across {{agency_name}} in {{town}} and wanted to reach out.

I'm {{sender_name}}, founder of ZapTask (www.zaptask.co.uk) — a UK platform that connects homeowners, landlords, property managers and tenants with trusted local tradespeople and construction professionals.

We're building our network of vetted builders and contractors across the UK, and companies like yours in {{town}} are exactly the kind of local partner we want to work with.

ZapTask helps construction businesses:
• Get in front of property managers and landlords who need reliable trades
• Receive enquiries for repairs, refurbishments, maintenance and project work in your area
• Spend less time on marketing while building a trusted local profile

We're onboarding a small number of construction partners in each area. If you're open to a quick 10-minute call to see whether it could work for {{agency_name}}, I'd be happy to chat.

You can find out more at www.zaptask.co.uk.

Best regards,
{{sender_name}}
Founder, ZapTask
www.zaptask.co.uk
TEXT,
            ],
        ];

        foreach ($templates as $template) {
            $existing = EstateAgentOutreachTemplate::query()
                ->where('name', $template['name'])
                ->first();

            $attributes = [
                'subject' => $template['subject'],
                'body' => $template['body'],
                'is_active' => true,
            ];

            if ($existing) {
                $existing->update($attributes);
                continue;
            }

            EstateAgentOutreachTemplate::create([
                'id' => (string) Str::uuid(),
                'name' => $template['name'],
                ...$attributes,
            ]);
        }
    }
}
