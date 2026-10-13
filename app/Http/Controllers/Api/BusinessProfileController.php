<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class BusinessProfileController extends Controller
{
    public function show(Request $request)
    {
        $profile = $request->user()->businessProfile
            ?? new BusinessProfile(['business_name' => $request->user()->name]);

        return response()->json($profile);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'business_name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'gstin' => 'nullable|string|max:20',
            'state' => 'nullable|string|max:100',
            'state_code' => 'nullable|string|max:5',
            'currency' => 'nullable|string|max:5',
            'timezone' => 'nullable|string|max:50',
            'bank_details' => 'nullable|array',
            'upi_id' => 'nullable|string|max:100',
            'invoice_prefix' => 'nullable|string|max:20',
            'receipt_prefix' => 'nullable|string|max:20',
            'financial_year_start' => 'nullable|string|max:5',
            'invoice_number_reset' => 'nullable|boolean',
            'default_payment_terms' => 'nullable|string|max:255',
            'invoice_footer' => 'nullable|string',
            'tax_config' => 'nullable|array',
        ]);

        $profile = $request->user()->businessProfile;

        if ($profile) {
            $profile->update($data);
        } else {
            $profile = $request->user()->businessProfile()->create($data);
        }

        if ($request->hasFile('logo')) {
            $request->validate(['logo' => 'image|max:2048']);
            if ($profile->logo) {
                Storage::disk('public')->delete($profile->logo);
            }
            $profile->update(['logo' => $request->file('logo')->store('logos', 'public')]);
        }

        activity()->causedBy(Auth::user())->log('Business profile updated');

        return response()->json($profile->fresh());
    }
}
