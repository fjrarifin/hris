<?php

namespace App\Services;

use App\Http\Services\WhatsAppService;
use Illuminate\Support\Str;

class HrisWhatsAppAgent
{
    public function __construct(
        private readonly OllamaChatService $ollama,
        private readonly WhatsAppService $whatsApp
    ) {}

    public function handleWebhook(array $payload): array
    {
        if (! (bool) config('services.hris_agent.enabled', true)) {
            return ['status' => 'ignored', 'reason' => 'agent_disabled'];
        }

        if ($this->isFromMe($payload)) {
            return ['status' => 'ignored', 'reason' => 'from_me'];
        }

        $sender = $this->extractSender($payload);
        $message = $this->extractMessage($payload);

        if ($sender === '' || $message === '') {
            return ['status' => 'ignored', 'reason' => 'missing_sender_or_message'];
        }

        if (! $this->senderAllowed($sender)) {
            return ['status' => 'ignored', 'reason' => 'sender_not_allowed'];
        }

        $question = $this->stripTrigger($message);

        if ($question === null || $question === '') {
            return ['status' => 'ignored', 'reason' => 'trigger_not_matched'];
        }

        $answer = $this->answer($question);
        $sent = $this->whatsApp->sendMessage($sender, $answer);

        return [
            'status' => $sent ? 'sent' : 'send_failed',
            'sender' => $sender,
            'question' => $question,
        ];
    }

    private function answer(string $question): string
    {
        $fixedAnswer = $this->fixedAnswer($question);

        if ($fixedAnswer !== null) {
            return $fixedAnswer;
        }

        return $this->ollama->chat($question, $this->systemPrompt())
            ?? 'Maaf, AI lokal sedang belum siap menjawab. Coba lagi sebentar lagi ya.';
    }

    private function fixedAnswer(string $question): ?string
    {
        $normalized = Str::of($question)
            ->lower()
            ->replaceMatches('/[^a-z0-9\s]/', ' ')
            ->squish()
            ->toString();

        if (str_contains($normalized, 'apa itu hris') || str_contains($normalized, 'hris itu apa')) {
            return 'HRIS adalah Human Resource Information System, yaitu aplikasi untuk membantu HR mengelola data karyawan, absensi, jadwal, cuti/izin, approval, dan proses administrasi karyawan dalam satu sistem.';
        }

        if (
            str_contains($normalized, 'kapan')
            && (str_contains($normalized, 'dibuat') || str_contains($normalized, 'pertama') || str_contains($normalized, 'rilis'))
        ) {
            return 'Berdasarkan riwayat repository lokal, aplikasi HRIS ini pertama tercatat dibuat pada '.config('services.hris_agent.created_date', '2026-05-28').'.';
        }

        if (in_array($normalized, ['help', 'bantuan', 'menu'], true)) {
            return "Saya bisa bantu jawab pertanyaan HRIS dasar dulu.\nContoh:\n- Apa itu HRIS?\n- Kapan aplikasi ini pertama kali dibuat?";
        }

        return null;
    }

    private function systemPrompt(): string
    {
        return implode("\n", [
            'Kamu adalah AI agent WhatsApp untuk aplikasi HRIS internal.',
            'Jawab dalam Bahasa Indonesia yang singkat, sopan, dan praktis.',
            'Konteks aplikasi: HRIS mengelola karyawan, absensi, jadwal, cuti, izin, lembur, approval, dashboard HR, dan payroll.',
            'Jika tidak tahu data spesifik dari aplikasi, katakan bahwa data itu belum tersedia dan jangan mengarang.',
        ]);
    }

    private function stripTrigger(string $message): ?string
    {
        $message = trim($message);
        $trigger = trim((string) config('services.hris_agent.trigger_prefix', ''));

        if ($trigger === '') {
            return $message;
        }

        $lowerMessage = Str::lower($message);
        $lowerTrigger = Str::lower($trigger);

        foreach ([$lowerTrigger, '@'.$lowerTrigger] as $prefix) {
            if (str_starts_with($lowerMessage, $prefix)) {
                return trim(substr($message, strlen($prefix)));
            }
        }

        return null;
    }

    private function senderAllowed(string $sender): bool
    {
        $allowed = config('services.hris_agent.allowed_senders', []);

        if (! is_array($allowed) || $allowed === []) {
            return true;
        }

        return in_array($sender, $allowed, true)
            || in_array(preg_replace('/[^0-9]/', '', $sender), $allowed, true);
    }

    private function extractSender(array $payload): string
    {
        return $this->firstString($payload, [
            'phone',
            'from',
            'sender',
            'jid',
            'chat',
            'remoteJid',
            'key.remoteJid',
            'data.phone',
            'data.from',
            'data.sender',
            'data.jid',
            'data.chat',
            'data.key.remoteJid',
            'messages.0.key.remoteJid',
        ]);
    }

    private function extractMessage(array $payload): string
    {
        return $this->firstString($payload, [
            'message',
            'text',
            'body',
            'conversation',
            'content',
            'message.text',
            'message.body',
            'message.conversation',
            'message.extendedTextMessage.text',
            'data.message',
            'data.text',
            'data.body',
            'data.message.text',
            'data.message.body',
            'data.message.conversation',
            'data.message.extendedTextMessage.text',
            'messages.0.message.conversation',
            'messages.0.message.extendedTextMessage.text',
        ]);
    }

    private function firstString(array $payload, array $paths): string
    {
        foreach ($paths as $path) {
            $value = data_get($payload, $path);

            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return '';
    }

    private function isFromMe(array $payload): bool
    {
        foreach (['fromMe', 'isFromMe', 'key.fromMe', 'data.fromMe', 'data.key.fromMe', 'messages.0.key.fromMe'] as $path) {
            $value = data_get($payload, $path);

            if ($value === true || $value === 'true' || $value === 1 || $value === '1') {
                return true;
            }
        }

        return false;
    }
}
