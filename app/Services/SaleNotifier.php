<?php

namespace App\Services;

use App\Models\ActivityNotification;
use App\Models\Sale;
use App\Models\User;

class SaleNotifier
{
    /**
     * هر فروش تکمیل‌شده به بخش‌هایی که آن کاربر به آن‌ها دسترسی دارد خبر می‌دهد.
     */
    public function notify(Sale $sale): void
    {
        $amount = number_format((float) $sale->total_afn, 0);
        $customer = $sale->customer_name_snapshot ?: 'Walk-in';
        $summary = "{$sale->invoice_number} — {$customer} — {$amount} AFN";

        $sections = [
            [
                'module'  => 'dashboard',
                'section' => 'dashboard',
                'title'   => 'New sale on dashboard',
                'body'    => $summary,
                'url'     => '/dashboard',
            ],
            [
                'module'  => 'sales',
                'section' => 'sales',
                'title'   => 'Sale invoice saved',
                'body'    => $summary,
                'url'     => '/sales/'.$sale->id,
            ],
            [
                'module'  => 'inventory',
                'section' => 'inventory',
                'title'   => 'Stock reduced',
                'body'    => $summary,
                'url'     => '/inventory',
            ],
            [
                'module'  => 'sales',
                'section' => 'receipts',
                'title'   => 'Sale receipt ready',
                'body'    => $summary,
                'url'     => '/sales/'.$sale->id.'/receipt',
            ],
        ];

        if ($sale->customer_id) {
            $sections[] = [
                'module'  => 'customers',
                'section' => 'customers',
                'title'   => 'Customer account updated',
                'body'    => $summary.' — balance '.number_format((float) $sale->remaining_due_afn, 0).' AFN',
                'url'     => '/customers/'.$sale->customer_id,
            ];
        }

        if ((float) $sale->total_investor_profit_afn > 0) {
            $sections[] = [
                'module'  => 'investor-profit-entries',
                'section' => 'investor-profit-entries',
                'title'   => 'Investor profit from sale',
                'body'    => $summary,
                'url'     => '/investor-profit-entries',
            ];
        }

        $users = User::query()
            ->notTrashed()
            ->where('status', 'active')
            ->get(['id', 'role']);

        $now = now();
        $rows = [];

        foreach ($users as $user) {
            foreach ($sections as $section) {
                if (! $user->canAccessModule($section['module'])) {
                    continue;
                }

                $rows[] = [
                    'user_id' => $user->id,
                    'section' => $section['section'],
                    'title'   => $section['title'],
                    'body'    => $section['body'],
                    'url'     => $section['url'],
                    'sale_id' => $sale->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            ActivityNotification::insert($chunk);
        }
    }
}
