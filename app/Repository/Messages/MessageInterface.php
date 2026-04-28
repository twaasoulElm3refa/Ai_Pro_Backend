<?php

namespace App\Repository\Messages;

interface MessageInterface
{
    public function send($data);
}
