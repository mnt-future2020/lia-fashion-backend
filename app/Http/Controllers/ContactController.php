<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        // Validate Form Data
        $validator = Validator::make($request->all(), [
            'firstName' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'message' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            // Send Email
            Mail::send('mail.contact', ['data' => $request->all()], function ($message) {
                $message->to('sheik@mntfuture.com')
                       ->subject('New Contact Inquiry');
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Your message has been sent successfully!'
            ]);

        } catch (\Exception $e) {
            Log::error('Contact form error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send message. Please try again later.'
            ], 500);
        }
    }
}
