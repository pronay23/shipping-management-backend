<?php

namespace App\Http\Controllers;

use App\Http\Resources\JournalEntryResource;
use App\Models\JournalEntry;
use App\Services\JournalService;

class JournalEntryController extends Controller
{
    public function __construct(public JournalService $journal) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(
            JournalEntryResource::collection(
                JournalEntry::withCount('lines')->latest('id')->get()
            )
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(JournalEntry $journalEntry)
    {
        return response()->json(
            new JournalEntryResource($journalEntry->load('lines.account', 'lines.invoice'))
        );
    }

    /**
     * Approve a draft journal entry.
     */
    public function approve(JournalEntry $journalEntry)
    {
        $entry = $this->journal->approve($journalEntry, $this->currentUserId());

        return response()->json(
            new JournalEntryResource($entry->load('lines.account', 'lines.invoice'))
        );
    }

    /**
     * Void a journal entry (delete draft / reverse approved).
     */
    public function void(JournalEntry $journalEntry)
    {
        $this->journal->void($journalEntry, $this->currentUserId());

        return response()->json(null, 204);
    }

    private function currentUserId(): ?int
    {
        return auth('sanctum')->id();
    }
}