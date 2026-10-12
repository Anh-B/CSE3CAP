<?php

namespace App\Http\Controllers;

use App\Models\Reflection;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

class JournalExportController extends Controller
{
    public function export()
    {
        try {
            $reflections = Reflection::where('user_id', auth()->id())
             ->with(['assessments' => fn ($q) => $q->with('assessor:id,name')->latest()])
             ->latest() 
             ->get(); 
            $pdf = Pdf::loadView('journal.export', [
                'reflections' => $reflections
            ]);

            return $pdf->download('reflection-journal.pdf');

        } catch (\Exception $e) {
            // Keep the technical detail in the server log; don't send it to the browser.
            Log::error('Journal export failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to export journal. Please try again later.'
            ], 500);
        }
    }
}