<?php

class SendRequest
{
    private string $url;
    private string $method;
    private mixed $data;
    private array $headers;

    public function __construct(string $url, string $method = 'GET', mixed $data = null, array $headers = [])
    {
        $this->url = $url;
        $this->method = strtoupper($method);
        $this->data = $data;
        $this->headers = $headers;
    }

    public function send(): array
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->method);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Define um tempo limite de segurança
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        // Trabalha com uma cópia local dos headers
        $requestHeaders = $this->headers;

        // Se houver dados para enviar
        if ($this->data !== null) {
            // Se já for uma string (ex: JSON pré-formatado ou Form URL Encoded), envia direto
            // Se for array/objeto, converte para JSON
            $payload = is_string($this->data) ? $this->data : json_encode($this->data);
            
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            
            // Adiciona o Content-Type se não foi definido manualmente
            if (!in_array('Content-Type: application/json', $requestHeaders)) {
                $requestHeaders[] = 'Content-Type: application/json';
            }
        }

        if (!empty($requestHeaders)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        // Trata erro de conexão cURL
        if ($response === false) {
            return [
                'status_code' => 0,
                'error' => $curlError,
                'response' => null
            ];
        }

        // Decodifica JSON se possível, senão retorna o texto bruto
        $decodedResponse = json_decode($response, true);
        
        return [
            'status_code' => $httpCode,
            'response' => (json_last_error() === JSON_ERROR_NONE) ? $decodedResponse : $response
        ];
    }
}