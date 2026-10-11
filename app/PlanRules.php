<?php
declare(strict_types=1);
namespace QualPacote;

use InvalidArgumentException;

final class PlanRules
{
    public static function normalize(array $rules): array
    {
        $category = (string) ($rules['category'] ?? 'mobile');
        $repeat = (string) ($rules['repeat_policy'] ?? 'unknown');
        if (!in_array($category, ['mobile','router'], true) || !in_array($repeat, ['unknown','sequential','parallel'], true)) throw new InvalidArgumentException('Categoria ou regra de compras inválida.');
        $result = ['category'=>$category, 'repeat_policy'=>$repeat, 'allows_balance'=>!empty($rules['allows_balance'])];
        foreach (['family','requires_family','combination_group'] as $field) {
            $value = trim((string) ($rules[$field] ?? ''));
            if ($value !== '' && !preg_match('/\A[a-z0-9_-]{1,60}\z/D', $value)) throw new InvalidArgumentException('Use um código simples na família e compatibilidade.');
            $result[$field] = $value;
        }
        if ($result['requires_family'] !== '' && $result['requires_family'] === $result['family']) throw new InvalidArgumentException('O aditivo precisa de uma família diferente da sua própria.');
        if (($result['requires_family'] !== '' || $result['combination_group'] !== '') && $result['family'] === '') throw new InvalidArgumentException('Indique a família antes de confirmar dependência ou compatibilidade.');
        $end = trim((string) ($rules['offer_ends_at'] ?? ''));
        if ($end !== '' && (!preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $end) || date('Y-m-d', strtotime($end) ?: 0) !== $end)) throw new InvalidArgumentException('Data final da oferta inválida.');
        $result['offer_ends_at'] = $end;
        $step = (int) ($rules['voice_step_seconds'] ?? 0);
        if (!in_array($step, [0,1,60], true)) throw new InvalidArgumentException('Unidade de cobrança de voz inválida.');
        $result['voice_step_seconds'] = $step;
        $incompatible = $rules['incompatible_families'] ?? [];
        if (is_string($incompatible)) $incompatible = array_filter(array_map('trim', explode(',', $incompatible)));
        foreach ($incompatible as $family) if (!preg_match('/\A[a-z0-9_-]{1,60}\z/D', $family)) throw new InvalidArgumentException('Família incompatível inválida.');
        $result['incompatible_families'] = array_values(array_unique($incompatible));
        $key = (string) ($rules['source_offer_key'] ?? '');
        if ($key !== '' && !preg_match('/\A[a-z0-9_:-]{1,100}\z/D',$key)) throw new InvalidArgumentException('Identificador da oferta inválido.');
        $result['source_offer_key'] = $key;
        return $result;
    }
}
