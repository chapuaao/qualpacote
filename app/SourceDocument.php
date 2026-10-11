<?php
declare(strict_types=1);
namespace QualPacote;

final class SourceDocument
{
    public static function text(string $body): string
    {
        $body = preg_replace('#<(script|style|nav|header|footer)\b[^>]*>.*?</\1>#si', ' ', $body) ?? $body;
        $body = preg_replace('#<(?:br|/p|/div|/li|/tr|/h[1-6]|/section)\b[^>]*>#i', "\n", $body) ?? $body;
        $text = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\xc2\xa0", "\r"], [' ', ''], $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        return trim(preg_replace('/\n\s*\n+/u', "\n", $text) ?? $text);
    }

    public static function resolve(string $base, string $href): ?string
    {
        $href = html_entity_decode(trim($href), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($href === '' || preg_match('/^(?:mailto:|tel:|javascript:|data:|#)/i', $href)) return null;
        if (str_starts_with($href, '//')) $href = 'https:' . $href;
        if (!preg_match('#^https?://#i', $href)) {
            $parts = parse_url($base);
            if (!$parts || empty($parts['host'])) return null;
            $reference = parse_url($href);
            if ($reference === false || isset($reference['scheme'])) return null;
            $relativePath = $reference['path'] ?? '';
            $path = $relativePath === '' ? ($parts['path'] ?? '/') : (str_starts_with($relativePath, '/') ? $relativePath : preg_replace('#/[^/]*$#', '/', $parts['path'] ?? '/') . $relativePath);
            $segments = [];
            foreach (explode('/', $path) as $segment) {
                if ($segment === '..') array_pop($segments);
                elseif ($segment !== '' && $segment !== '.') $segments[] = $segment;
            }
            $href = ($parts['scheme'] ?? 'https') . '://' . $parts['host'] . '/' . implode('/', $segments) . (str_ends_with($path,'/') && $segments !== [] ? '/' : '') . (isset($reference['query']) ? '?' . $reference['query'] : '');
        }
        $href = preg_replace('/#.*$/', '', $href) ?? $href;
        return SourceFetcher::allowed($href) ? $href : null;
    }

    public static function links(string $body, string $base): array
    {
        preg_match_all('/<a\b[^>]*href\s*=\s*([\'"])(.*?)\1[^>]*>(.*?)<\/a>/si', $body, $matches, PREG_SET_ORDER);
        $links = [];
        foreach ($matches as $match) {
            $url = self::resolve($base, $match[2]);
            $title = trim(self::text($match[3]));
            if (!$url || !preg_match('/plan|pacot|ofert|tarif|campanh|karga|kuia|taku|flex|movinet|konekta|afrinet|afrimix|socializa|\.pdf|mais/i', $url . ' ' . $title)) continue;
            if (parse_url($url, PHP_URL_HOST) !== parse_url($base, PHP_URL_HOST)) continue;
            $links[$url] = ['url'=>$url, 'title'=>$title ?: basename(parse_url($url, PHP_URL_PATH) ?: $url)];
        }
        return array_values($links);
    }

    public static function extract(string $adapter, string $text): array
    {
        $offers = [];
        if ($adapter === 'afrimix') {
            preg_match_all('/([\d.,]+)\s*(MB|GB)\s+(?:[\d.,]+\s*(?:MB|GB)\s+)?([\d.,]+)\s*Minutos\s+Kz\s*([\d., ]+)\s+MARCA\s*(\*123\*7\*([1-4])#)/i', $text, $rows, PREG_SET_ORDER);
            foreach ($rows as $row) {
                $days = ['1'=>1,'2'=>3,'3'=>3,'4'=>7][$row[6]];
                $price = self::number($row[4]);
                $offer = self::offer('Afrimix ' . $days . 'D ' . number_format($price, 0, ',', '.'), $price, $days, $row[5], self::mb($row[1], $row[2]));
                $offer['benefits'][] = ['type'=>'VOICE','quantity'=>self::number($row[3]),'unit'=>'MIN','network_scope'=>'ONNET'];
                $offer['rules'] = ['source_offer_key'=>'afrimix:option-' . $row[6], 'family'=>'afrimix','repeat_policy'=>'parallel','combination_group'=>'afrimix','allows_balance'=>true];
                $offer['notes'] = 'Chamadas exclusivamente Africell. Cada subscrição tem saldo e validade próprios; o saldo expira. Renovação opcional. Uma passagem da FAQ refere outras redes, em conflito com a descrição e restantes condições: confirmar em revisão.';
                $offers[] = $offer;
            }
            preg_match_all('/MARCA\s*(\*123\*7\*\d+#)/i', $text, $codes);
            if (count(array_unique($codes[1])) !== count($offers)) return [];
        } elseif ($adapter === 'movinet') {
            preg_match_all('/(?:AGORA\s+)?([\d.,]+)\s*(MB|GB)\s+([\d.,]+)\s*KZ\s*\/\s*(?:(\d+)\s*DIAS?|(?<day>DIA))/i', $text, $rows, PREG_SET_ORDER);
            foreach ($rows as $row) {
                $days = !empty($row[4]) ? (int) $row[4] : 1;
                $price = self::number($row[3]);
                $offer = self::offer('Movinet ' . $days . 'D ' . $row[1] . ' ' . strtoupper($row[2]), $price, $days, (str_contains($text, '*200*' . (int) $price . '#') ? '*200*' . (int) $price . '#' : ''), self::mb($row[1], $row[2]));
                $offer['rules'] = ['family'=>'movinet'];
                $offer['notes'] = 'Confirmar elegibilidade e incompatibilidades na FAQ Movinet antes de publicar. Sem repetição ou combinação presumida.';
                $offers[] = $offer;
            }
        } elseif ($adapter === 'konekta4g') {
            preg_match_all('/Konekta\s+([\d.,]+)\s*GB\s+Kz\s*([\d., ]+)\s+([\d.,]+)\s*GB\s+(\d+)\s*dias/i', $text, $rows, PREG_SET_ORDER);
            foreach ($rows as $row) {
                $offer = self::offer('Konekta 4G ' . $row[3] . ' GB', self::number($row[2]), (int) $row[4], str_contains($text, '*123*5#') ? '*123*5#' : '', self::mb($row[3], 'GB'));
                $offer['rules'] = ['category'=>'router','family'=>'konekta4g'];
                $offer['notes'] = 'Requer SIM Konekta e MiFi/router compatível já disponíveis. Smartphone e chamadas não permitidos. Preço inclui IVA. Quota extra após esgotar não quantificada: excluída do cálculo. Equipamento não incluído no preço.';
                $offers[] = $offer;
            }
        }
        return $offers;
    }

    private static function offer(string $name, float $price, int $days, string $code, float $mb): array
    {
        return ['name'=>$name,'price_kz'=>$price,'validity_days'=>$days,'activation_code'=>$code,'benefits'=>[['type'=>'DATA','quantity'=>$mb,'unit'=>'MB','network_scope'=>'ALL']], 'rules'=>[], 'notes'=>''];
    }

    private static function number(string $value): float
    {
        $value = str_replace(' ', '', trim($value));
        if (preg_match('/^\d{1,3}(?:[.,]\d{3})+$/', $value)) return (float) str_replace([',','.'], '', $value);
        return (float) str_replace(',', '.', $value);
    }

    private static function mb(string $value, string $unit): float
    {
        return self::number($value) * (strtoupper($unit) === 'GB' ? 1024 : 1);
    }
}
