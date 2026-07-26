<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DepositSuccessMail extends Mailable
{
    use Queueable, SerializesModels;

    public $amount;

    public $currency;

    public $name;

    public function __construct($amount, $name, $currency = 'USD')
    {
        $this->amount = $amount;
        $this->name = $name;
        $this->currency = strtoupper((string) $currency);
    }

    /**
     * Email subject + meta
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Wallet deposit completed',
        );
    }

    /**
     * View + data
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.deposit_success',
            with: [
                'amount' => $this->amount,
                'currency' => $this->currency,
                'name' => $this->name,
            ],
        );
    }

    /**
     * Attachments (optional)
     */
    public function attachments(): array
    {
        return [];
    }
}
