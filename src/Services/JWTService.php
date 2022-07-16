<?php

namespace App\Services;

use DateTimeImmutable;

class JWTService
{
    // On génère un token

    public function generate(array $header, array $payload, string $secret, int $validity = 3600): string
    {

        if ($validity > 0) {
            $now = new DateTimeImmutable();
            $exp = $now->getTimestamp() + $validity;

            $payload['iat'] = $now->getTimestamp();
            $payload['exp'] = $exp;
        }

        
        // On encode en base64
        $base64Header = base64_encode(json_encode($header));
        $base64Payload = base64_encode(json_encode($payload));

        // On nettoie les valeurs encodées
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], $base64Header);
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], $base64Payload);

        // On génère la signature
        $secret = base64_encode($secret);
        $signature =hash_hmac('sha256',$base64Header. '.' .$base64Payload, $secret, true);

        $base64Signature = base64_encode($signature);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], $base64Signature);

        // On cré le token
        $jwt = $base64Header . '.' . $base64Payload . '.' . $base64Signature;
        return $jwt;
    }

    // On vérifie si le token est correctement formé
    public function isValid(string $token): bool
    {
        return preg_match('/^[a-zA-Z0-9\-\_\=]+\.[a-zA-Z0-9\-\_\=]+\.[a-zA-Z0-9\-\_\=]+$/',$token) === 1;
    }

    // On récupère le payload

    public function getPayload(string $token): array
    {
        // On demande le payload
        $array = explode('.', $token);

        // On décode le payload
        $payload = (array) json_decode(base64_decode($array[1], true));
        return $payload;
    }

      // On récupère le header

      public function getHeader(string $token): array
      {
          // On demonde le payload
          $array = explode('.', $token);
  
          // On décode le payload
          $header = (array) json_decode(base64_decode($array[0], true));
          return $header;
      }

    // On vérifie si le token a expiré
    public function isExpired(string $token): bool
    {
        $payload = $this->getPayload($token);

        $now = new DateTimeImmutable();
        return $payload['exp'] < $now->getTimestamp();
    }

    // On récupère la signature du token
    public function check(string $token, string $secret)
    {
        // On récupère le header et le payload
        $header = $this->getHeader($token);
        $payload = $this->getHeader($token);

        // On regénère le token
        $verifyToken = $this->generate($header, $payload, $secret,0);
        // dd($verifyToken);
        // return $verifyToken;
        if ($token === $verifyToken) {
           return true;
        }else{
            return false;
        }
    }
}