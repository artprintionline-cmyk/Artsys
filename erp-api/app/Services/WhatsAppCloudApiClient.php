<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhatsAppCloudApiClient
{
    /**
     * @param string $apiVersion ex: v19.0
     * @param string $phoneNumberId
     * @param string $accessToken
     * @param string $to Numero E.164 sem "+" (ex: 5511999999999)
     * @param string $message
     * @return array{provider_message_id?:string, raw:array}
     */
    public function sendText(string $apiVersion, string $phoneNumberId, string $accessToken, string $to, string $message): array
    {
        $url = "https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $message,
            ],
        ];

        $res = Http::withToken($accessToken)
            ->acceptJson()
            ->asJson()
            ->post($url, $payload);

        $raw = $res->json();

        if (! $res->successful()) {
            $msg = is_array($raw) && isset($raw['error']['message']) ? (string) $raw['error']['message'] : 'Falha ao enviar WhatsApp.';
            throw new \RuntimeException($msg);
        }

        $providerMessageId = null;
        if (is_array($raw) && isset($raw['messages'][0]['id'])) {
            $providerMessageId = (string) $raw['messages'][0]['id'];
        }

        return [
            'provider_message_id' => $providerMessageId,
            'raw' => is_array($raw) ? $raw : ['raw' => $raw],
        ];
    }

    /**
     * @return array{provider_message_id?:string, raw:array}
     */
    public function sendTemplate(string $apiVersion, string $phoneNumberId, string $accessToken, string $to, string $templateName, string $languageCode = 'pt_BR'): array
    {
        $url = "https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $languageCode,
                ],
            ],
        ];

        $res = Http::withToken($accessToken)
            ->acceptJson()
            ->asJson()
            ->post($url, $payload);

        $raw = $res->json();

        if (! $res->successful()) {
            $msg = is_array($raw) && isset($raw['error']['message']) ? (string) $raw['error']['message'] : 'Falha ao enviar WhatsApp (template).';
            throw new \RuntimeException($msg);
        }

        $providerMessageId = null;
        if (is_array($raw) && isset($raw['messages'][0]['id'])) {
            $providerMessageId = (string) $raw['messages'][0]['id'];
        }

        return [
            'provider_message_id' => $providerMessageId,
            'raw' => is_array($raw) ? $raw : ['raw' => $raw],
        ];
    }
}
