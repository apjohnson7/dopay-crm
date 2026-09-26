<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Country;
use App\Models\FinanceForm;
use App\Models\NumberSequence;
use Illuminate\Support\Facades\DB;

/**
 * Gap-free, race-safe numbering. Each scope (e.g. INV:UG, FORM:A:UGD) has its own counter,
 * incremented inside a row lock so two officers can never receive the same number.
 */
class NumberingService
{
    public function next(string $scope): int
    {
        return DB::transaction(function () use ($scope) {
            $row = NumberSequence::where('scope', $scope)->lockForUpdate()->first()
                ?? NumberSequence::create(['scope' => $scope, 'last_value' => 0]);
            $row->last_value++;
            $row->save();

            return $row->last_value;
        });
    }

    /** Invoice, payment, receipt and expense numbers, e.g. DOPAY-UG-INV-2026-000237. */
    public function document(Country $country, string $doc, ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');
        $seq = $this->next("{$doc}:{$country->iso2}:{$year}");

        return strtr(config('dopay.document_pattern'), [
            '{CC}' => $country->iso2, '{DOC}' => $doc, '{YYYY}' => (string) $year, '{SEQ}' => str_pad((string) $seq, 6, '0', STR_PAD_LEFT),
        ]);
    }

    /** Finance form references in the formats used on the paper templates, e.g. UGD2026-A-017, UGD-HQ-2026-B-004. */
    public function formReference(FinanceForm $form): string
    {
        $def = config('dopay.forms.'.$form->type);
        $country = $form->country ?? Country::findOrFail($form->country_id);
        $branch = $form->branch ?? Branch::findOrFail($form->branch_id);
        $year = match ($form->type) {
            'G' => substr((string) $form->datum('month'), 0, 4),
            'F' => (string) $form->datum('year'),
            default => now()->format('Y'),
        };
        $pattern = $def['pattern'];
        $seq = '';
        if (str_contains($pattern, '{###}')) {
            $scope = 'FORM:'.$form->type.':'.$country->doc_code.($form->type === 'B' ? ':'.$branch->code : '').':'.$year;
            $seq = str_pad((string) $this->next($scope), 3, '0', STR_PAD_LEFT);
        }
        $period = match ($form->datum('period')) {
            'Annual' => 'FY',
            'Quarterly' => 'Q'.$form->datum('quarter'),
            default => 'M'.str_pad((string) $form->datum('month'), 2, '0', STR_PAD_LEFT),
        };

        return strtr($pattern, [
            '{CODE}' => $country->doc_code, '{BR}' => $branch->code, '{YYYY}' => $year, '{###}' => $seq,
            '{PERIOD}' => $period, '{MM}' => substr((string) $form->datum('month'), 5, 2),
        ]);
    }
}
