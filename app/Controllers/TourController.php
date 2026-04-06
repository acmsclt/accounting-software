<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Database;

/**
 * Marks tour as seen in the DB (server-side complement to localStorage).
 */
class TourController extends Controller
{
    /** POST /tour/complete  — called via fetch() from tour.js */
    public function complete(): void
    {
        if (!Auth::check()) { $this->json(['ok' => false]); return; }

        $tour = preg_replace('/[^a-z_-]/', '', $_POST['tour'] ?? '');
        if (!$tour) { $this->json(['ok' => false]); return; }

        $userId    = Auth::user()['id'] ?? null;
        $companyId = Auth::companyId();

        // Upsert into user meta table or activity log (reuse activity_logs)
        // We just log it — no dedicated table needed
        Database::insert('activity_logs', [
            'user_id'     => $userId,
            'company_id'  => $companyId,
            'action'      => 'tour.completed',
            'model'       => 'tour',
            'description' => "Completed tour: {$tour}",
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        $this->json(['ok' => true, 'tour' => $tour]);
    }

    /** GET /tour/reset — dev helper to reset all tours for current user */
    public function reset(): void
    {
        if (!Auth::check()) { $this->redirect('/login'); }
        $this->json(['ok' => true, 'message' => 'Clear localStorage ap_tours_seen to reset. Server-side tours have no flag to reset.']);
    }
}
