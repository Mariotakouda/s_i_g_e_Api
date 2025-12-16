<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserWelcomeEmail extends Mailable // Si vous voulez la queue, ajoutez implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $userName; 
    
    public function __construct(string $userName)
    {
        $this->userName = $userName;
    }

    /**
     * Supprimez cette méthode ! Elle est obsolète et entre en conflit.
     * public function build() { ... } 
     */

    public function envelope(): Envelope
    {
        return new Envelope(
            // Le sujet est bien défini ici.
            subject: 'Bienvenue dans notre communauté S_i_g_e !', 
            // Si vous voulez changer l'expéditeur pour celui-ci
            // from: new Address('support@monapp.com', 'Support Mon App'),
        );
    }

    public function content(): Content
    {
        return new Content(
            // ATTENTION: Nous utilisons 'html' car vous avez créé un template HTML/Blade complet,
            // et non un template Markdown.
            html: 'emails.welcome', 
            // Nous n'utilisons plus 'markdown: '
        );
    }

    public function attachments(): array
    {
        return [];
    }
}