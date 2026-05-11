<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RfidTag;
use App\Models\User;

class RfidController extends Controller
{
    /**
     * Display a listing of recently scanned tags.
     */
    public function index()
    {
        // eager load user to avoid N+1
        $tags = RfidTag::with('user')->latest()->limit(100)->get();
        $users = \App\Models\User::orderBy('name')->get();
        return view('rfid.index', compact('tags', 'users'));
    }

    /**
     * Assign a tag to a user via the web UI.
     */
    public function assign(Request $request, RfidTag $tag)
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $tag->user_id = $data['user_id'];
        $tag->save();

        return back()->with('success', 'RFID tag ' . $tag->tag . ' linked to user.');
    }

    /**
     * Receive a scan from the reader.
     *
     * Expected body: { "tag": "123456ABC", "user_id": optional }
     */
    public function scan(Request $request)
    {

        if ($request->key != "RFID_SECRET_123") {
            return response()->json(['error' => 'unauthorized'], 403);
        }

        $data = $request->validate([
            'tag' => ['required', 'string'],
            'user_id' => ['nullable', 'exists:users,id'],
        ]);

        // find or create tag record
        $tag = RfidTag::firstOrCreate(
            ['tag' => $data['tag']],
            ['user_id' => $data['user_id'] ?? null]
        );

        // if a user_id is provided and the tag isn't linked yet, attach it
        if (!empty($data['user_id']) && $tag->user_id !== $data['user_id']) {
            $tag->user_id = $data['user_id'];
            $tag->save();
        }

        return response()->json(['success' => true, 'tag' => $tag]);
    }

    public function last()
    {
        return response()->json([
            'uid' => \Illuminate\Support\Facades\Cache::get('last_rfid')
        ]);
    }
}
