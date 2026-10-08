<?php

namespace App\Http\Controllers;

use App\Models\Reflection;
use Barryvdh\DomPDF\Facade\Pdf;

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
            return response()->json([
                'message' => 'Failed to export journal',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}