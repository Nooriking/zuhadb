<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Static guard: app/ must not keep calling dropped tables/models.
 *
 * NOTE: InvestorWallet is an intentional façade over investor_wallet_balances
 * (table investor_wallets is gone). It is excluded from basename skips of
 * pattern InvestorWallet:: but raw `investor_wallets` table usage elsewhere fails.
 */
class NoDeadReferencesTest extends TestCase
{
    public function test_no_live_references_to_dropped_tables_in_app(): void
    {
        $forbiddenPatterns = [
            'InvestorProfitEntry',
            'InvestmentProductAllocation',
            'InvestmentPurchaseAllocation',
            'CustomerTransaction',
            'CustomerBalance',
            'investor_profit_entries',
            'customer_transactions',
            'customer_balances',
            'investment_product_allocations',
            'investment_purchase_allocations',
            'investors_legacy',
        ];

        $appDir = base_path('app');
        $violations = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($appDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        // Definition files for removed / façade surfaces (mention their own names).
        $skipBasenames = [
            'InvestorProfitEntry.php',
            'InvestmentProductAllocation.php',
            'InvestmentPurchaseAllocation.php',
            'CustomerTransaction.php',
            'CustomerBalance.php',
            'InvestorWallet.php',
        ];

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            if (str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'Migrations'.DIRECTORY_SEPARATOR)) {
                continue;
            }
            if (in_array($file->getBasename(), $skipBasenames, true)) {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            foreach ($forbiddenPatterns as $pattern) {
                if (str_contains($content, $pattern)) {
                    $violations[] = $file->getPathname().' → '.$pattern;
                }
            }

            if (str_contains($content, 'investor_wallets')) {
                $violations[] = $file->getPathname().' → investor_wallets';
            }
        }

        $this->assertEmpty(
            $violations,
            "ارجاعات زنده به جداول حذف‌شده پیدا شد:\n".implode("\n", $violations)
        );
    }

    public function test_no_product_purchase_date_references(): void
    {
        $file = app_path('Http/Controllers/ProductController.php');
        if (! file_exists($file)) {
            $this->assertTrue(true);

            return;
        }

        $content = file_get_contents($file);

        $this->assertDoesNotMatchRegularExpression(
            "/Product::where(?:Not)?Null\('purchase_date'/",
            $content,
            "ارجاع به purchase_date روی Product در {$file} پیدا شد"
        );
        $this->assertDoesNotMatchRegularExpression(
            "/Product::onlyTrash\(\)[\s\S]{0,80}whereNull\('purchase_date'/",
            $content,
            "ارجاع به purchase_date روی Product در {$file} پیدا شد"
        );
    }
}
