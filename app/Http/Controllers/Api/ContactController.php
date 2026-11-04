<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonRespone;
use App\Models\Contact;

class ContactController extends Controller
{

    /**
     * POST /contacts - submit a contact message.
     * Authenticated: linked vie user_id, unlimited submissions.
     * Guest: single submission enforced via session_token + ip_address.
     * 
     * @param  Request      $request user request contact
     */
    public function store(Request $request)
    {   
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email',
            'subject' => 'nullable|string|max:191',
            'message' => 'required|string'
        ]);

        $user = auth('sanctum')->user();

        if ($user) {
            $data['user_id'] = $user->id;
            $data['email'] = $data['email'] ?? $user->email;
        } else {
            // Guest - enforce single contact per session/IP
            $ipAddress = $request->ip();
            $userAgent = $request->userAgent();

            // Fingerprint: IP + User-Agent hash for guest tracking
            $fingerprint = hash('sha256', $ipAddress . '|' . $userAgent);

            $alreadyContacted = Contact::whereNull('user_id')
                ->where('session_token', $fingerprint)->exists();
            
            if($alreadyContacted) {
                return response()->json([
                    'message' => 'You have already submited a contact request. Please create an account for further communication.',
                ], 429);
            }

            $data['session_token'] = $fingerprint;
            $data['ip_address'] = $ipAddress;
        }

        return response()->json(Contact::create($data), 201);
    }

    /**
     * GET /admin/contacts - list all messages (admin/owner)
     * 
     * @param Request $request user request to list all messages
     */
    public function index(Request $request)
    {
        return response()->json(
            Contact::with('user:id,name,email')
                ->when($request->status, fn($q, $s) => 
                $q->where('status', $s))->latest()->paginate(20)
        );
    }


    /**
     * GET /admin/contacts/{contact} - view single message, mark as read
     * 
     * @param  Contact      $contact single message to view
     * @return JsonResponse 200 ok contact message retrieve successfully
     */
    public function show(Contact $contact): JsonResponse
    {
        if ($contact->status === 'unread') {
            $contact->update(['status' => 'read']);
        }

        return response()->json($contact->load('user:id,name,email'));
    }

    /**
     * PATCH /admin/contacts/{contact} - update status (admin/owner)
     * 
     * @param  Request $request privileged user request to update contact message
     * @param  Contact $contact contact message to update
     * @return JsonResponse 200 ok the request message successfully delivered 
     */
    public function update(Request $request, Contact $contact): JsonResponse 
    {
        $contact->update($request->validate([
            'status' => 'required|in:unread,read,replied',
        ]));

        return response()->json($contact);
    }

    /**
     * DELETE /admin/contacts/{contact} - delete (admin/owner)
     * 
     * @param  Contact      $contact contact message to be deleted
     * @return JsonResponse 204 contact successfully deleted without message
     */
    public function destroy(Contact $contact): JsonResponse 
    {
        $contact->delete();

        return response()->json(null, 204);
    }
}
