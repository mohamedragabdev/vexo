<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Http\Requests\Chat\UpdateMessage;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $conversation = Conversation::where('id', $request->conversation_id)->first();
        $receiver = User::where('phone', $request->receiver_phone)->first();
        if ($conversation) {
            $message = Message::create([
                'sender_id' => Auth::id(),
                'receiver_id' => $receiver->id,
                'conversation_id' => $request->conversation_id,
                'message' => $request->message,
            ]);
            event(new MessageSent($message));
            return response()->json([
                'message' => 'message sent successfully',
                'model' => $message
            ]);
        } else {
            $conversation = Conversation::create([
                'sender_id' => Auth::id(),
                'receiver_id' => $receiver->id,
            ]);
            $message = Message::create([
                'sender_id' => Auth::id(),
                'receiver_id' => $receiver->id,
                'conversation_id' => $conversation->id,
                'message' => $request->message,
            ]);
            event(new MessageSent($message));
            return response()->json([
                'message' => 'message sent successfully',
                'model' => $message
            ]);
        }
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMessage $request, int $id)
    {
        $user = Auth::user();

        $message = Message::where('sender_id', $user->id)
            ->findOrFail($id);

        $this->authorize('update', $message);

        $message->update($request->only('message'));

        return response()->json([
            'message' => 'Successfully updated',
            'content' => $message,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id)
    {
        $user = Auth::user();
        $message = Message::where('sender_id',$user->id)->findOrFail($id);
        $this->authorize('delete', $$message);
        $message->delete();
        return response()->json([
            'message' => 'successful deleted',
        ], 200);
    }
}
