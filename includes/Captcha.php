<?php
class Captcha {
    public static function verify($response) {
        $secret = getenv('HCAPTCHA_SECRET');
        if (!$secret || !$response) return false;

        $ch = curl_init('https://hcaptcha.com/siteverify');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'secret' => $secret,
                'response' => $response
            ])
        ]);
        $out = curl_exec($ch);
        curl_close($ch);

        $json = json_decode($out, true);
        return isset($json['success']) && $json['success'] === true;
    }
}
