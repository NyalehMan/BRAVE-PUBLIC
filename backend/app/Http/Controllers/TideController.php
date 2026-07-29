<?php

namespace App\Http\Controllers;

use App\Models\BruneiTide;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TideController extends Controller
{
    public function nearest(Request $request)
    {
        $request->validate([
            'district' => ['required', 'string'],
            'datetime' => ['required', 'date'],
        ]);

        $district = $this->normalizeDistrict($request->query('district'));
        $datetime = Carbon::parse($request->query('datetime'), 'Asia/Brunei');

        $nearest = BruneiTide::where('district', $district)
            ->get()
            ->sortBy(function ($row) use ($datetime) {
                return abs(Carbon::parse($row->tide_datetime)->diffInSeconds($datetime, false));
            })
            ->first();

        return response()->json([
            'district' => $district,
            'requested_datetime' => $datetime->toDateTimeString(),
            'tide' => $nearest?->tide_height,
            'tide_type' => $nearest?->tide_type,
            'tide_datetime' => $nearest?->tide_datetime,
            'station' => $nearest?->station,
        ]);
    }

    private function normalizeDistrict(string $value): string
    {
        $text = strtolower($value);

        if (str_contains($text, 'belait') || str_contains($text, 'kb')) {
            return 'Belait';
        }

        if (str_contains($text, 'tutong')) {
            return 'Tutong';
        }

        if (str_contains($text, 'temburong') || str_contains($text, 'bangar')) {
            return 'Temburong';
        }

        return 'Brunei Muara';
    }
}