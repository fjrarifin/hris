<?php

namespace App\Http\Services;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Exception;

class EmailService
{
    /**
     * Send email dengan konfigurasi SMTP
     *
     * @param string|array $to Email penerima
     * @param string $subject Subject email
     * @param string $body HTML body email
     * @param array $options Opsi tambahan (cc, bcc, attachments, dll)
     * @return bool
     */
    public function send($to, $subject, $body, $options = [])
    {
        try {
            Mail::html($body, function ($message) use ($to, $subject, $options) {
                $message->to($to)
                    ->subject($subject)
                    ->from(
                        config('mail.from.address'),
                        config('mail.from.name')
                    );

                // CC
                if (!empty($options['cc'])) {
                    $message->cc($options['cc']);
                }

                // BCC
                if (!empty($options['bcc'])) {
                    $message->bcc($options['bcc']);
                }

                // Reply-To
                if (!empty($options['replyTo'])) {
                    $message->replyTo($options['replyTo']);
                }

                // Attachments
                if (!empty($options['attachments']) && is_array($options['attachments'])) {
                    foreach ($options['attachments'] as $file) {
                        $message->attach($file);
                    }
                }
            });

            Log::info('Email sent successfully', [
                'to' => $to,
                'subject' => $subject,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to send email', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send email menggunakan Mailable class
     *
     * @param string|array $to Email penerima
     * @param Mailable $mailable Mailable class instance
     * @return bool
     */
    public function sendMailable($to, Mailable $mailable)
    {
        try {
            Mail::to($to)->send($mailable);

            Log::info('Mailable sent successfully', [
                'to' => $to,
                'mailable' => class_basename($mailable),
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to send mailable', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send email ke multiple recipients
     *
     * @param array $recipients [['email' => 'user@example.com', 'name' => 'User Name'], ...]
     * @param string $subject
     * @param string $body
     * @param array $options
     * @return bool
     */
    public function sendToMultiple($recipients, $subject, $body, $options = [])
    {
        try {
            foreach ($recipients as $recipient) {
                $email = is_array($recipient) ? $recipient['email'] : $recipient;
                $this->send($email, $subject, $body, $options);
            }

            Log::info('Bulk email sent successfully', [
                'count' => count($recipients),
                'subject' => $subject,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to send bulk email', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Test koneksi SMTP
     *
     * @return array
     */
    public function testConnection()
    {
        try {
            Mail::raw('Test email connection', function ($message) {
                $message->to(config('mail.from.address'))
                    ->subject('SMTP Connection Test')
                    ->from(config('mail.from.address'), config('mail.from.name'));
            });

            return [
                'success' => true,
                'message' => 'SMTP connection successful',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'SMTP connection failed: ' . $e->getMessage(),
                'error' => $e,
            ];
        }
    }
}