<?php
declare(strict_types=1);
namespace QualPacote;
use RuntimeException;

final class SourceFetcher
{
    private const HOSTS = ['unitel.ao','www.unitel.ao','africell.ao','www.africell.ao','movicel.co.ao','www.movicel.co.ao','www2.movicel.co.ao'];

    public static function allowed(string $url): bool
    {
        $parts = parse_url($url);
        return $parts !== false && in_array(strtolower($parts['scheme'] ?? ''), ['http','https'], true)
            && in_array(strtolower($parts['host'] ?? ''), self::HOSTS, true)
            && !isset($parts['user'], $parts['pass']) && !isset($parts['user']) && !isset($parts['port'])
            && !preg_match('/[\x00-\x20\x7f]/', $url) && strlen($url) <= 600;
    }

    public function fetch(string $url): array
    {
        if (!function_exists('curl_init')) throw new RuntimeException('A extensão cURL do PHP é necessária.');
        for ($redirect = 0; $redirect <= 4; $redirect++) {
            if (!self::allowed($url)) throw new RuntimeException('Fonte fora dos domínios oficiais permitidos.');
            $body = ''; $location = null; $type = null; $tooLarge = false;
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_FOLLOWLOCATION=>false, CURLOPT_CONNECTTIMEOUT=>8, CURLOPT_TIMEOUT=>25,
                CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2, CURLOPT_USERAGENT=>'QualPacote/2 source-review',
                CURLOPT_PROTOCOLS=>CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_WRITEFUNCTION=>static function ($curl, string $chunk) use (&$body, &$tooLarge): int {
                    if (strlen($body) + strlen($chunk) > 8 * 1024 * 1024) { $tooLarge = true; return 0; }
                    $body .= $chunk; return strlen($chunk);
                },
                CURLOPT_HEADERFUNCTION=>static function ($curl, string $line) use (&$location, &$type): int {
                    if (stripos($line, 'Location:') === 0) $location = trim(substr($line, 9));
                    if (stripos($line, 'Content-Type:') === 0) $type = trim(substr($line, 13));
                    return strlen($line);
                },
            ]);
            $ok = curl_exec($ch); $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $error = curl_error($ch); curl_close($ch);
            if ($ok === false) throw new RuntimeException($tooLarge ? 'Documento excede o limite de 8 MB; requer revisão manual.' : ($error ?: 'Falha de acesso.'));
            if ($status >= 300 && $status < 400 && $location) {
                $url = SourceDocument::resolve($url, $location) ?? throw new RuntimeException('Redireccionamento fora dos domínios oficiais.');
                continue;
            }
            if ($status < 200 || $status >= 300 || trim($body) === '') throw new RuntimeException('Resposta HTTP inválida: ' . $status);
            return ['body'=>$body,'status'=>$status,'content_type'=>$type,'url'=>$url];
        }
        throw new RuntimeException('Excesso de redireccionamentos.');
    }
}
