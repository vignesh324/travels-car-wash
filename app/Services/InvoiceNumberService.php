<?php

namespace App\Services;

use App\Models\BusinessProfile;
use Illuminate\Support\Facades\DB;

class InvoiceNumberService
{
    public function generate(): string
    {
        $profile = BusinessProfile::first();
        $prefix = $profile?->invoice_prefix ?? 'INV';
        $fy = $this->financialYear($profile);

        return DB::transaction(function () use ($prefix, $fy) {
            $seq = DB::table('invoice_sequences')
                ->where('prefix', $prefix)
                ->where('financial_year', $fy)
                ->lockForUpdate()
                ->first();

            if ($seq) {
                $next = $seq->last_number + 1;
                DB::table('invoice_sequences')
                    ->where('id', $seq->id)
                    ->update(['last_number' => $next, 'updated_at' => now()]);
            } else {
                $next = 1;
                DB::table('invoice_sequences')->insert([
                    'prefix' => $prefix,
                    'financial_year' => $fy,
                    'last_number' => $next,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $suffix = $fy ? "/{$fy}" : '';
            return "{$prefix}{$suffix}/" . str_pad($next, 4, '0', STR_PAD_LEFT);
        });
    }

    public function generateReceipt(): string
    {
        $profile = BusinessProfile::first();
        $prefix = $profile?->receipt_prefix ?? 'RCP';
        $fy = $this->financialYear($profile);

        return DB::transaction(function () use ($prefix, $fy) {
            $seq = DB::table('receipt_sequences')
                ->where('prefix', $prefix)
                ->where('financial_year', $fy)
                ->lockForUpdate()
                ->first();

            if ($seq) {
                $next = $seq->last_number + 1;
                DB::table('receipt_sequences')
                    ->where('id', $seq->id)
                    ->update(['last_number' => $next, 'updated_at' => now()]);
            } else {
                $next = 1;
                DB::table('receipt_sequences')->insert([
                    'prefix' => $prefix,
                    'financial_year' => $fy,
                    'last_number' => $next,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $suffix = $fy ? "/{$fy}" : '';
            return "{$prefix}{$suffix}/" . str_pad($next, 4, '0', STR_PAD_LEFT);
        });
    }

    private function financialYear(?BusinessProfile $profile): string
    {
        if (!$profile?->invoice_number_reset) {
            return '';
        }
        $fyStart = (int) ($profile->financial_year_start ?? 4);
        $now = now();
        $year = $now->month >= $fyStart ? $now->year : $now->year - 1;
        return $year . '-' . substr($year + 1, 2);
    }
}
