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

        $this->authorize('view', $user);

        $conversations = $user->senderConversations()->with('receiver')->get();

        return ConversationResource::collection($conversations);
    }



    /**
     * Display the specified resource.
     */
    public function show(int $conversation)
    {
        $user =Auth::user();
        $this->authorize('view',$user);
        $conversation = $user->senderConversations()->with('receiver')->where('id',$conversation)->firstOrFail();
        $conversation->messages()->update(['status'=>'seen']);
        return new ConversationResource($conversation);

    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $conversation)
    {
        $user = Auth::user();
        $this->authorize('delete',$user);
        Conversation::findOrFail($conversation)->delete();
        return response()->json([
            'message'=>'success deleted',

        ],200);
        
        
    }
}
