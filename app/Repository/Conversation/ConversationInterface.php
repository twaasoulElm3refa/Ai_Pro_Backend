<?php

namespace App\Repository\Conversation;

interface ConversationInterface
{
    public function index();
    public function create($data);
    public function show($uuid);
    public function destroy($uuid);
}
