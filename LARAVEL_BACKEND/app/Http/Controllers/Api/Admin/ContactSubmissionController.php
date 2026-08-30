<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactSubmissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ContactSubmission::query()->orderByDesc('created_at');

        if ($request->query('unread') === '1') {
            $query->whereNull('read_at');
        }

        $items = $query->limit(200)->get()->map(fn (ContactSubmission $row) => $this->toArray($row));

        return response()->json([
            'submissions' => $items->values()->all(),
            'unreadCount' => ContactSubmission::query()->whereNull('read_at')->count(),
        ]);
    }

    public function markRead(ContactSubmission $contactSubmission): JsonResponse
    {
        if ($contactSubmission->read_at === null) {
            $contactSubmission->read_at = now();
            $contactSubmission->save();
        }

        return response()->json([
            'success' => true,
            'submission' => $this->toArray($contactSubmission->fresh()),
        ]);
    }

    public function markUnread(ContactSubmission $contactSubmission): JsonResponse
    {
        $contactSubmission->read_at = null;
        $contactSubmission->save();

        return response()->json([
            'success' => true,
            'submission' => $this->toArray($contactSubmission),
        ]);
    }

    public function destroy(ContactSubmission $contactSubmission): JsonResponse
    {
        $contactSubmission->delete();

        return response()->json(['success' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(ContactSubmission $row): array
    {
        return [
            'id' => (string) $row->id,
            'name' => $row->name,
            'email' => $row->email,
            'message' => $row->message,
            'ipAddress' => $row->ip_address,
            'isRead' => $row->read_at !== null,
            'readAt' => $row->read_at?->toIso8601String(),
            'createdAt' => $row->created_at?->toIso8601String(),
        ];
    }
}