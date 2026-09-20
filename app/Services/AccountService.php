<?php

namespace App\Services;

use App\Models\ChartOfAccount;

class AccountService
{
    public const AR_CONTROL_CODE = '1100';
    public const AP_CONTROL_CODE = '2100';
    public const CASH_CODE = '1010';

    /**
     * Return the per-customer Accounts Receivable account for the given
     * customer, creating a new `1100.{seq}` sub-account when it does not
     * already exist.
     */
    public function ensureCustomerArAccount(?string $customerName): ChartOfAccount
    {
        $customerName = trim((string) $customerName);

        if ($customerName === '') {
            return $this->controlAccount();
        }

        $name = "AR – {$customerName}";

        $account = ChartOfAccount::where('name', $name)->first();

        if ($account !== null) {
            return $account;
        }

        $control = $this->controlAccount();

        return ChartOfAccount::create([
            'code' => $this->nextArCode(),
            'name' => $name,
            'type' => 'asset',
            'parent_id' => $control->id,
            'is_active' => true,
            'is_system' => false,
        ]);
    }

    /**
     * Return the per-vendor Accounts Payable account for the given
     * vendor, creating a new `2100.{seq}` sub-account when it does not
     * already exist.
     */
    public function ensureVendorApAccount(?string $vendorName): ChartOfAccount
    {
        $vendorName = trim((string) $vendorName);

        if ($vendorName === '') {
            return $this->apControlAccount();
        }

        $name = "AP – {$vendorName}";

        $account = ChartOfAccount::where('name', $name)->first();

        if ($account !== null) {
            return $account;
        }

        $control = $this->apControlAccount();

        return ChartOfAccount::create([
            'code' => $this->nextApCode(),
            'name' => $name,
            'type' => 'liability',
            'parent_id' => $control->id,
            'is_active' => true,
            'is_system' => false,
        ]);
    }

    /**
     * Get the AR control account that per-customer sub-accounts hang off of.
     */
    public function controlAccount(): ChartOfAccount
    {
        return ChartOfAccount::where('code', self::AR_CONTROL_CODE)->firstOrFail();
    }

    /**
     * Get the AP control account that per-vendor sub-accounts hang off of.
     */
    public function apControlAccount(): ChartOfAccount
    {
        return ChartOfAccount::where('code', self::AP_CONTROL_CODE)->firstOrFail();
    }

    /**
     * Derive the next available `1100.{seq}` sub-account code.
     */
    private function nextArCode(): string
    {
        $last = ChartOfAccount::where('code', 'like', self::AR_CONTROL_CODE.'.%')
            ->orderByDesc('code')
            ->value('code');

        $sequence = $last === null ? 1 : ((int) substr($last, strlen(self::AR_CONTROL_CODE) + 1)) + 1;

        return sprintf('%s.%03d', self::AR_CONTROL_CODE, $sequence);
    }

    /**
     * Derive the next available `2100.{seq}` sub-account code.
     */
    private function nextApCode(): string
    {
        $last = ChartOfAccount::where('code', 'like', self::AP_CONTROL_CODE.'.%')
            ->orderByDesc('code')
            ->value('code');

        $sequence = $last === null ? 1 : ((int) substr($last, strlen(self::AP_CONTROL_CODE) + 1)) + 1;

        return sprintf('%s.%03d', self::AP_CONTROL_CODE, $sequence);
    }
}
