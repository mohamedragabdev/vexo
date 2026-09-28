<?php

namespace App\Http\Controllers;

use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Stripe\ApiResource;

class ConversationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        $conversations = Conversation::where(function ($query) use ($user) {
            $query->where('sender_id', $user->id)->orWhere('receiver_id', $user->id);
        })->with(['sender', 'receiver'])->latest()->get();



        return ConversationResource::collection($conversations);
    }



    /**
     * Display the specified resource.
     */
    public function show(int $conversation)
    {
        $user = Auth::user();
        $conversation = Conversation::where(function ($query) use ($user) {
            $query->where('sender_id', $user->id)->orWhere('receiver_id', $user->id);
        })->findOrFail($conversation);
        $this->authorize('view', $conversation);


        $conversation->messages()
            ->where('receiver_id', $user->id)
            ->where('status', '!=', 'seen')
            ->update(['status' => 'seen']);
        return new ConversationResource($conversation);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $conversation)
    {
        $user = Auth::user();
        $conversation = Conversation::where(function ($query) use ($user) {
            $query->where('sender_id', $user->id)->orWhere('receiver_id', $user->id);
        })->findOrFail($conversation);
        $this->authorize('delete', $conversation);
        $conversation->delete();
        return response()->json([
            'message' => 'success deleted',

        ], 200);
    }
}
