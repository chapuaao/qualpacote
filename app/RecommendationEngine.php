<?php
declare(strict_types=1);

namespace QualPacote;

final class RecommendationEngine
{
    private const SERVICES = ['data', 'voice', 'sms', 'social'];

    public function recommend(array $plans, array $profile, array $tariffs = []): array
    {
        $profile = $this->normalizeProfile($profile);
        $candidates = [];
        $profile['active_families'] = [];
        foreach ($plans as $plan) {
            if (in_array((int) ($plan['id'] ?? 0), $profile['active_plan_ids'], true) && !empty($plan['rules']['family'])) {
                $profile['active_families'][(int) $plan['operator_id']][] = $plan['rules']['family'];
            }
        }

        foreach ($plans as $plan) {
            if (($plan['rules']['category'] ?? 'mobile') !== $profile['category']) continue;
            if (!empty($plan['rules']['offer_ends_at']) && $plan['rules']['offer_ends_at'] < date('Y-m-d')) continue;
            $candidate = $this->planCandidate($plan, $profile, $tariffs);
            if ($candidate !== null) {
                $candidates[] = $candidate;
                if (($plan['rules']['repeat_policy'] ?? 'unknown') === 'parallel') {
                    $max = (int) floor($profile['budget_kz'] / (float) $plan['price_kz']);
                    $unit = $this->planCandidate($plan, $profile, $tariffs, 1);
                    $minimum = $this->minimumPurchases($unit, $profile);
                    foreach (array_unique([$minimum,$max]) as $count) if ($count > $candidate['cycles'] && $count <= $max) $candidates[] = $this->planCandidate($plan, $profile, $tariffs, $count);
                }
            }
        }

        $candidates = array_merge($candidates, $this->combinationCandidates($candidates, $profile, $tariffs));

        foreach ($profile['category'] === 'mobile' ? $tariffs : [] as $tariff) {
            $candidate = $this->balanceCandidate($tariff, $profile);
            if ($candidate !== null) $candidates[] = $candidate;
        }

        if ($candidates === []) return [];

        $maxima = array_fill_keys(self::SERVICES, 0.0);
        foreach ($candidates as $candidate) {
            if (!empty($candidate['conditional'])) continue;
            foreach (self::SERVICES as $service) {
                $maxima[$service] = max($maxima[$service], (float) ($candidate['capacity'][$service] ?? 0));
            }
        }
        foreach ($candidates as $candidate) {
            foreach (self::SERVICES as $service) {
                if ($maxima[$service] <= 0) {
                    $maxima[$service] = max($maxima[$service], (float) ($candidate['capacity'][$service] ?? 0));
                }
            }
        }

        foreach ($candidates as &$candidate) {
            $candidate = array_merge($candidate, $this->assess($candidate, $profile, $maxima));
            $candidate['remaining_budget'] = max(0.0, $profile['budget_kz'] - $candidate['effective_cost']);
        }
        unset($candidate);

        usort($candidates, static function (array $a, array $b): int {
            $conditional = ((int) $a['conditional']) <=> ((int) $b['conditional']);
            if ($conditional !== 0) return $conditional;

            $meets = ((int) $b['meets_requirements']) <=> ((int) $a['meets_requirements']);
            if ($meets !== 0) return $meets;

            $score = $b['score'] <=> $a['score'];
            if ($score !== 0) return $score;

            $cost = $a['effective_cost'] <=> $b['effective_cost'];
            if ($cost !== 0) return $cost;

            return strcmp((string) $a['title'], (string) $b['title']);
        });

        $unique = [];
        foreach ($candidates as $candidate) {
            $key = $candidate['result_key'] ?? ($candidate['kind'] . ':' . (int) (($candidate['plan'] ?? $candidate['tariff'])['id'] ?? 0) . ':' . $candidate['cycles']);
            $unique[$key] ??= $candidate;
        }
        return array_slice(array_values($unique), 0, 12);
    }

    private function normalizeProfile(array $profile): array
    {
        $services = array_values(array_unique(array_filter(
            array_map(static fn ($value) => strtolower(trim((string) $value)), (array) ($profile['services'] ?? [])),
            static fn ($value) => in_array($value, self::SERVICES, true)
        )));

        if ($services === []) {
            $goal = (string) ($profile['goal'] ?? 'balanced');
            $services = match ($goal) {
                'data' => ['data'],
                'voice' => ['voice'],
                'social' => ['social'],
                default => ['data', 'voice'],
            };
        }

        $priority = strtolower((string) ($profile['priority'] ?? 'balanced'));
        if ($priority !== 'balanced' && !in_array($priority, $services, true)) $priority = 'balanced';

        $duration = max(0, min(365, (int) ($profile['duration_days'] ?? 30)));

        $schedule = (string) ($profile['schedule'] ?? 'day');
        if (!in_array($schedule, ['day', 'mixed', 'night', 'late_night'], true)) $schedule = 'day';

        $callScope = (string) ($profile['call_scope'] ?? 'mixed');
        if (!in_array($callScope, ['same', 'mixed', 'other'], true)) $callScope = 'mixed';

        $voiceMode = (string) ($profile['voice_mode'] ?? 'max');
        if (!in_array($voiceMode, ['max', 'minutes', 'calls'], true)) $voiceMode = 'max';

        $dataMode = (string) ($profile['data_mode'] ?? 'max');
        if (!in_array($dataMode, ['max', 'minimum'], true)) $dataMode = 'max';

        $smsMode = (string) ($profile['sms_mode'] ?? 'max');
        if (!in_array($smsMode, ['max', 'minimum'], true)) $smsMode = 'max';

        $socialMode = (string) ($profile['social_mode'] ?? 'max');
        if (!in_array($socialMode, ['max', 'minimum'], true)) $socialMode = 'max';

        $minutes = max(0.0, (float) ($profile['minutes'] ?? 0));
        if ($voiceMode === 'calls') {
            $calls = max(0, (int) ($profile['calls'] ?? 0));
            $average = max(0.1, min(30.0, (float) ($profile['avg_call_minutes'] ?? 3)));
            $minutes = $calls * $average;
        }

        return [
            'budget_kz' => max(0.0, (float) ($profile['budget_kz'] ?? 0)),
            'services' => $services,
            'priority' => $priority,
            'duration_days' => $duration,
            'schedule' => $schedule,
            'call_scope' => $callScope,
            'data_mode' => $dataMode,
            'voice_mode' => $voiceMode,
            'sms_mode' => $smsMode,
            'social_mode' => $socialMode,
            'data_mb' => max(0.0, (float) ($profile['data_gb'] ?? 0)) * 1024,
            'voice_min' => $minutes,
            'sms' => max(0.0, (float) ($profile['sms_count'] ?? 0)),
            'social_mb' => max(0.0, (float) ($profile['social_gb'] ?? 0)) * 1024,
            'social_apps' => array_values(array_unique(array_map('strtolower', (array) ($profile['social_apps'] ?? [])))),
            'sms_scope' => in_array($profile['sms_scope'] ?? '', ['same','other','mixed'], true) ? $profile['sms_scope'] : $callScope,
            'category' => ($profile['category'] ?? '') === 'router' ? 'router' : 'mobile',
            'router_ready' => !empty($profile['router_ready']),
            'calls' => max(0, (int) ($profile['calls'] ?? 0)),
            'avg_call_minutes' => max(0.1, min(30.0, (float) ($profile['avg_call_minutes'] ?? 3))),
            'active_plan_ids' => array_values(array_filter(array_map('intval', (array) ($profile['active_plan_ids'] ?? [])), static fn ($id) => $id > 0)),
        ];
    }

    private function planCandidate(array $plan, array $profile, array $tariffs, ?int $purchases = null): ?array
    {
        $price = (float) ($plan['price_kz'] ?? 0);
        $budget = $profile['budget_kz'];
        if ($price <= 0 || $price > $budget) return null;

        $validity = max(1, (int) ($plan['validity_days'] ?? 1));
        $duration = $profile['duration_days'];
        $requiredCycles = $duration > 0 ? (int) ceil($duration / $validity) : 1;
        $repeat = $plan['rules']['repeat_policy'] ?? 'unknown';
        $cycles = $repeat === 'unknown' ? 1 : max(1, min($purchases ?? $requiredCycles, (int) floor($budget / $price)));
        $effectiveCost = $price * $cycles;
        $coverageDays = $duration > 0 ? min($duration, $validity * $cycles) : $validity;
        $coverageRatio = $duration > 0 ? min(1.0, $coverageDays / $duration) : 1.0;

        $nominal = array_fill_keys(self::SERVICES, 0.0);
        $effective = array_fill_keys(self::SERVICES, 0.0);
        $restrictions = [];
        $notices = [];
        $sharedGroups = [];
        $networkPools = ['voice'=>['ONNET'=>0.0,'OFFNET'=>0.0], 'sms'=>['ONNET'=>0.0,'OFFNET'=>0.0]];

        foreach ($plan['benefits'] ?? [] as $benefit) {
            $quantity = (float) ($benefit['quantity'] ?? 0);
            if ($quantity <= 0) continue;

            $service = $this->serviceKey((string) ($benefit['type'] ?? ''), (string) ($benefit['unit'] ?? ''));
            if ($service === null) continue;

            $sharedKey = $this->sharedWalletKey($benefit);
            if ($sharedKey !== null) {
                $sharedGroups[$sharedKey][] = $benefit;
                continue;
            }

            $totalQuantity = $quantity * $cycles;
            $dailyCapMb = $this->dailyCapMb($benefit);
            if ($dailyCapMb !== null && in_array($service, ['data', 'social'], true) && $profile['duration_days'] > 0) {
                $periodCap = $dailyCapMb * max(1, $coverageDays);
                $totalQuantity = min($totalQuantity, $periodCap);
                $notices['Este benefício é libertado diariamente: ' . $this->quantityLabel($service, $dailyCapMb) . ' por dia/noite. Para os ' . $coverageDays . ' dias cobertos, entram no cálculo no máximo ' . $this->quantityLabel($service, $totalQuantity) . (str_starts_with((string) ($benefit['label'] ?? ''), 'DAILY_RELEASE:') ? '; a carteira diária acumula apenas dentro da validade.' : '; o saldo diário não acumula.')] = true;
            }

            $timeFactor = $this->timeFactor($benefit['start_time'] ?? null, $benefit['end_time'] ?? null, $profile['schedule']);
            $networkFactor = in_array($service, ['voice', 'sms'], true)
                ? $this->networkFactor(strtoupper((string) ($benefit['network_scope'] ?? 'ALL')), $profile[$service === 'sms' ? 'sms_scope' : 'call_scope'])
                : 1.0;
            $factor = $timeFactor * $networkFactor;
            if (!empty($benefit['app_scope'])) {
                if ($service === 'data') $service = 'social';
                if (!$this->appsCovered((string) $benefit['app_scope'], $profile['social_apps'])) {
                    $factor = 0.0;
                    $restrictions[$this->quantityLabel($service, $totalQuantity) . ' só servem para ' . $benefit['app_scope'] . '; não cobrem todas as aplicações escolhidas. Escolha as aplicações para confirmar a utilização.'] = true;
                }
            }

            $nominal[$service] += $totalQuantity;
            $scope = strtoupper((string) ($benefit['network_scope'] ?? 'ALL'));
            $mixedNetwork = in_array($service, ['voice','sms'], true) && $profile[$service === 'sms' ? 'sms_scope' : 'call_scope'] === 'mixed' && $scope !== 'ALL';
            if ($mixedNetwork && isset($networkPools[$service][$scope])) {
                $networkPools[$service][$scope] += $totalQuantity * $timeFactor;
            } else {
                $effective[$service] += $totalQuantity * $factor;
            }

            foreach ($this->restrictionLabels($benefit, $service, $totalQuantity, $profile, $timeFactor, $networkFactor) as $label) {
                $restrictions[$label] = true;
            }

            if (!empty($benefit['app_scope'])) {
                $apps = trim((string) $benefit['app_scope']);
                $notices[$this->quantityLabel($service, $totalQuantity) . " são exclusivos para {$apps}; fora destas aplicações, esse volume não funciona como internet geral."] = true;
            }

            $benefitLabel = trim((string) ($benefit['label'] ?? ''));
            if ($benefitLabel !== '' && str_starts_with(strtoupper($benefitLabel), 'FUP:')) {
                $notices['Política de utilização: ' . preg_replace('/^FUP:/i', '', $benefitLabel)] = true;
            }
        }

        foreach ($sharedGroups as $key => $group) {
            $walletProfile = $profile;
            $walletProfile['voice_step_seconds'] = (int) ($plan['rules']['voice_step_seconds'] ?? 0);
            $this->applySharedWallet($group, $cycles, $walletProfile, $nominal, $effective, $restrictions, $notices, $networkPools);
        }

        foreach ($networkPools as $service => $pools) {
            if (array_sum($pools) <= 0) continue;
            $effective[$service] += min($pools['ONNET'], $pools['OFFNET']);
            $restrictions[$this->quantityLabel($service, $pools['ONNET']) . ' para a mesma rede e ' . $this->quantityLabel($service, $pools['OFFNET']) . ' para outras redes. Como escolheu várias redes sem indicar a repartição, só entra o volume que pode ser utilizado em qualquer destino; as carteiras não são intercambiáveis.'] = true;
        }

        $step = (int) ($plan['rules']['voice_step_seconds'] ?? 0);
        if ($profile['voice_mode'] === 'calls' && $step > 0) {
            $physical = $profile['avg_call_minutes'] * 60;
            $factor = $physical / (ceil($physical / $step) * $step);
            $effective['voice'] *= $factor;
            foreach ($networkPools['voice'] as &$quantity) $quantity *= $factor;
            unset($quantity);
            $restrictions['Chamadas estimadas pela duração média indicada, com arredondamento de cada chamada à unidade de cobrança confirmada (' . $step . ' segundos).'] = true;
        }
        $effective['sms'] = floor($effective['sms']);
        $capacity = $this->allocateData($effective, $profile);
        if (in_array('data', $profile['services'], true) && in_array('social', $profile['services'], true)) {
            $restrictions['Internet e redes sociais são consumos adicionais. Os dados gerais foram repartidos entre os dois usos, sem contar o mesmo volume duas vezes.'] = true;
        }

        [$equivalent, $equivalentComplete, $equivalentTariff] = $this->equivalentBalanceValue($plan, $effective, $profile, $tariffs);

        $requirements = $this->taggedLines((string) ($plan['notes'] ?? ''), 'REQUIREMENT');
        $requiredFamily = (string) ($plan['rules']['requires_family'] ?? '');
        if ($requiredFamily !== '') {
            $requirements = in_array($requiredFamily, $profile['active_families'][(int) ($plan['operator_id'] ?? 0)] ?? [], true)
                ? [] : ['Requer um plano ' . $requiredFamily . ' activo durante todo o período.'];
        }
        foreach (array_intersect($plan['rules']['incompatible_families'] ?? [], $profile['active_families'][(int) ($plan['operator_id'] ?? 0)] ?? []) as $family) $requirements[] = 'Não pode manter este pacote com o plano ' . $family . ' activo; a mudança pode apagar benefícios existentes.';
        if ($profile['category'] === 'router' && !$profile['router_ready']) $requirements[] = 'Requer SIM e MiFi/router compatíveis já disponíveis. O custo de equipamento não está incluído.';
        foreach ($requirements as $requirement) {
            $restrictions['Condição para activar: ' . $requirement] = true;
        }
        foreach (array_keys($notices) as $notice) {
            $restrictions[$notice] = true;
        }
        if ($repeat === 'unknown' && $requiredCycles > 1) $restrictions['Renovação não confirmada: calculámos apenas uma compra deste pacote.'] = true;
        if ($this->freshnessNotices($plan, $restrictions)) $requirements[] = 'A informação ultrapassou o prazo de confirmação; requer nova revisão antes de comprar.';

        return [
            'kind' => 'plan',
            'plan' => $plan,
            'tariff' => null,
            'operator_id' => (int) ($plan['operator_id'] ?? 0),
            'operator_name' => (string) ($plan['operator_name'] ?? ''),
            'title' => (string) ($plan['name'] ?? 'Plano'),
            'effective_cost' => $effectiveCost,
            'unit_price' => $price,
            'cycles' => $cycles,
            'coverage_days' => $coverageDays,
            'coverage_ratio' => $coverageRatio,
            'capacity' => $capacity,
            'nominal' => $nominal,
            'effective' => $effective,
            'restrictions' => array_keys($restrictions),
            'notices' => array_keys($notices),
            'conditional_requirements' => $requirements,
            'conditional' => $requirements !== [],
            'equivalent_balance_kz' => $equivalent,
            'equivalent_balance_complete' => $equivalentComplete,
            'equivalent_tariff_name' => $equivalentTariff,
            'balance_potentials' => [],
            'components' => [['plan'=>$plan, 'cycles'=>$cycles, 'cost'=>$effectiveCost, 'coverage_days'=>$coverageDays, 'schedule'=>$this->purchaseSchedule($cycles, $validity, $duration)]],
            'network_pools' => $networkPools,
            'result_key' => 'plan:' . (int) ($plan['id'] ?? 0) . ':' . (int) ($plan['version_id'] ?? 0) . ':' . $cycles,
        ];
    }

    private function purchaseSchedule(int $count, int $validity, int $duration): array
    {
        $windows = max(1, min($count, $duration > 0 ? (int) ceil($duration / $validity) : 1));
        $schedule = [];
        for ($i=0; $i<$windows; $i++) $schedule[] = ['day'=>1+$i*$validity, 'count'=>intdiv($count,$windows)+($i<$count%$windows ? 1 : 0)];
        return $schedule;
    }

    private function allocateData(array $effective, array $profile): array
    {
        $capacity = $effective;
        $general = (float) $effective['data'];
        $capacity['data'] = 0.0;
        $capacity['social'] = in_array('social', $profile['services'], true) ? (float) $effective['social'] : 0.0;
        $selected = array_values(array_intersect(['data','social'], $profile['services']));
        $targets = $this->minimumTargets($profile);
        foreach ($selected as $service) {
            $use = min($general, max(0.0, $targets[$service] - $capacity[$service]));
            $capacity[$service] += $use;
            $general -= $use;
        }
        $max = array_values(array_filter($selected, fn ($service) => $this->isMaximizeService($profile, $service)));
        if ($max === []) $max = $selected;
        foreach ($this->serviceWeights($max, $profile['priority']) as $service => $weight) $capacity[$service] += $general * $weight;
        return $capacity;
    }

    private function appsCovered(string $scope, array $requested): bool
    {
        if ($requested === []) return false;
        $scope = strtolower($scope);
        foreach ($requested as $app) {
            if ($app === 'other' || !preg_match('/(?<![a-z])' . preg_quote($app, '/') . '(?![a-z])/', $scope)) return false;
        }
        return true;
    }

    private function freshnessNotices(array $item, array &$notices): bool
    {
        if (empty($item['source_checked_at'])) return false;
        $date = strtotime((string) $item['source_checked_at']);
        if (!empty($item['source_observed_at'])) $date = max($date ?: 0, strtotime((string) $item['source_observed_at']) ?: 0);
        if ($date === false || $date < strtotime('-7 days')) $notices['Esta oferta precisa de nova verificação. Última confirmação: ' . $item['source_checked_at'] . '. Confirme as condições antes de comprar.'] = true;
        return $date === false || $date < strtotime('-30 days');
    }

    /** Bounded, explicit search: two compatible package types, plus verified balance. */
    private function combinationCandidates(array $singles, array $profile, array $tariffs): array
    {
        $result = [];
        $plans = [];
        foreach ($singles as $candidate) $plans[(int) $candidate['plan']['id']] = $candidate['plan'];
        $plans = array_values($plans);
        foreach ($plans as $i => $a) {
            foreach (array_slice($plans, $i + 1) as $b) {
                if (!empty($a['rules']['family']) && $a['rules']['family'] === ($b['rules']['family'] ?? '') && (($a['rules']['repeat_policy'] ?? '') !== 'parallel' || ($b['rules']['repeat_policy'] ?? '') !== 'parallel')) continue;
                $group = $a['rules']['combination_group'] ?? '';
                if ($group === '' || $group !== ($b['rules']['combination_group'] ?? '') || (int) $a['operator_id'] !== (int) $b['operator_id']) continue;
                $minA = $this->purchaseCountForPeriod($a, $profile);
                $minB = $this->purchaseCountForPeriod($b, $profile);
                $maxA = ($a['rules']['repeat_policy'] ?? '') === 'parallel' ? min(60, (int) floor(($profile['budget_kz'] - $minB * $b['price_kz']) / $a['price_kz'])) : $minA;
                for ($countA = $minA; $countA <= $maxA; $countA++) {
                    $budgetB = $profile['budget_kz'] - $countA * $a['price_kz'];
                    $maxB = ($b['rules']['repeat_policy'] ?? '') === 'parallel' ? max($minB, (int) floor($budgetB / $b['price_kz'])) : $minB;
                    $one = $this->planCandidate($a, $profile, $tariffs, $countA);
                    $unitB = $this->planCandidate($b, $profile, $tariffs, 1);
                    $needB = ($b['rules']['repeat_policy'] ?? '') === 'parallel' ? $this->minimumPurchases($unitB, $profile, $one) : $minB;
                    foreach (array_unique([$minB, $needB, $maxB]) as $countB) {
                        if ($countA * $a['price_kz'] + $countB * $b['price_kz'] > $profile['budget_kz']) continue;
                        $one = $this->planCandidate($a, $profile, $tariffs, $countA);
                        $two = $this->planCandidate($b, $profile, $tariffs, $countB);
                        if ($one && $two) $result[] = $this->combine([$one, $two], $profile);
                    }
                }
            }
        }
        foreach ($result as $pair) {
            $baseFamilies = []; $ids = [];
            foreach ($pair['components'] as $part) {
                $ids[] = (int) $part['plan']['id'];
                if (empty($part['plan']['rules']['requires_family'])) $baseFamilies[] = $part['plan']['rules']['family'] ?? '';
            }
            foreach ($plans as $third) {
                if (in_array((int) $third['id'], $ids, true) || !in_array($third['rules']['requires_family'] ?? '', array_filter($baseFamilies), true)) continue;
                if ((int) $third['operator_id'] !== $pair['operator_id'] || ($third['rules']['combination_group'] ?? '') !== ($pair['plan']['rules']['combination_group'] ?? '')) continue;
                $candidate = $this->planCandidate($third, $profile, $tariffs);
                if ($candidate && $candidate['effective_cost'] + $pair['effective_cost'] <= $profile['budget_kz']) $result[] = $this->combine([$pair, $candidate], $profile);
            }
        }
        foreach (array_merge($singles, $result) as $candidate) {
            $parts = $candidate['components'];
            if (array_filter($parts, static fn ($part) => empty($part['plan']['rules']['allows_balance']))) continue;
            $remaining = $profile['budget_kz'] - $candidate['effective_cost'];
            if ($remaining <= 0 || $candidate['conditional']) continue;
            $restProfile = $profile;
            $restProfile['budget_kz'] = $remaining;
            $targets = $this->minimumTargets($profile);
            foreach (['data'=>'data_mb','voice'=>'voice_min','sms'=>'sms','social'=>'social_mb'] as $service => $field) {
                $restProfile[$field] = max(0, $targets[$service] - $candidate['capacity'][$service]);
            }
            foreach ($profile['category'] === 'mobile' ? $tariffs : [] as $tariff) {
                if ((int) $tariff['operator_id'] !== $candidate['operator_id'] || empty($tariff['is_default'])) continue;
                $balance = $this->balanceCandidate($tariff, $restProfile);
                if (!$balance) continue;
                $combined = $this->combine([$candidate, $balance], $profile);
                $result[] = $combined;
            }
        }
        return $result;
    }

    private function minimumPurchases(array $unit, array $profile, ?array $prior = null): int
    {
        $count = $this->purchaseCountForPeriod($unit['plan'], $profile);
        $targets = $this->minimumTargets($profile);
        $need = static function (float $target, float $already, float $each): int {
            if ($target <= $already) return 0;
            return $each > 0 ? (int) ceil(($target-$already)/$each) : PHP_INT_MAX;
        };
        $data = $unit['effective']['data']; $social = $unit['effective']['social'];
        $before = $prior['effective'] ?? array_fill_keys(self::SERVICES,0.0);
        $count = max($count, $need($targets['data'], $before['data'], $data), $need($targets['data']+$targets['social'], $before['data']+$before['social'], $data+$social));
        foreach (['voice','sms'] as $service) {
            $scope = $profile[$service === 'sms' ? 'sms_scope' : 'call_scope'];
            if ($scope === 'mixed') {
                $a = $prior['network_pools'][$service] ?? ['ONNET'=>0.0,'OFFNET'=>0.0];
                $b = $unit['network_pools'][$service] ?? ['ONNET'=>0.0,'OFFNET'=>0.0];
                $allA = $before[$service] - min($a); $allB = $unit['effective'][$service] - min($b);
                foreach (['ONNET','OFFNET'] as $network) $count = max($count,$need($targets[$service],$allA+$a[$network],$allB+$b[$network]));
            } else $count = max($count,$need($targets[$service],$before[$service],$unit['effective'][$service]));
        }
        return $count;
    }

    private function purchaseCountForPeriod(array $plan, array $profile): int
    {
        if (($plan['rules']['repeat_policy'] ?? 'unknown') === 'unknown') return 1;
        return max(1, (int) ceil(max(1, $profile['duration_days']) / max(1, (int) $plan['validity_days'])));
    }

    private function combine(array $candidates, array $profile): array
    {
        $combined = $candidates[0];
        $combined['is_combination'] = true;
        $combined['components'] = [];
        $combined['effective_cost'] = 0.0;
        $combined['effective'] = array_fill_keys(self::SERVICES, 0.0);
        $combined['nominal'] = array_fill_keys(self::SERVICES, 0.0);
        $combined['restrictions'] = [];
        $combined['conditional_requirements'] = [];
        $combined['equivalent_balance_complete'] = false;
        $combined['equivalent_balance_kz'] = null;
        $families = $profile['active_families'][$combined['operator_id']] ?? [];
        $familyCoverage = array_fill_keys($families, max(1, $profile['duration_days']));
        foreach ($candidates as $candidate) foreach ($candidate['components'] ?? [] as $part) {
            if (!empty($part['plan']['rules']['family'])) {
                $family = $part['plan']['rules']['family'];
                $families[] = $family;
                $familyCoverage[$family] = max($familyCoverage[$family] ?? 0, $part['coverage_days']);
            }
        }
        $titles = [];
        $keys = [];
        $coverage = array_fill_keys($profile['services'], 0);
        $pools = ['voice'=>['ONNET'=>0.0,'OFFNET'=>0.0], 'sms'=>['ONNET'=>0.0,'OFFNET'=>0.0]];
        $networkCoverage = ['voice'=>['ALL'=>0,'ONNET'=>0,'OFFNET'=>0], 'sms'=>['ALL'=>0,'ONNET'=>0,'OFFNET'=>0]];
        foreach ($candidates as $candidate) {
            $titles[] = $candidate['title'];
            $keys[] = $candidate['result_key'] ?? 'balance:' . (int) $candidate['tariff']['id'] . ':' . (int) ($candidate['tariff']['version_id'] ?? 0);
            $combined['effective_cost'] += $candidate['effective_cost'];
            $combined['components'] = array_merge($combined['components'], $candidate['components'] ?? [['tariff'=>$candidate['tariff'],'cycles'=>1,'cost'=>$candidate['effective_cost'],'coverage_days'=>$candidate['coverage_days']]]);
            foreach (self::SERVICES as $service) {
                $combined['effective'][$service] += $candidate['effective'][$service];
                $combined['nominal'][$service] += $candidate['nominal'][$service];
            }
            foreach ($pools as $service => $_) {
                $partPools = $candidate['network_pools'][$service] ?? ['ONNET'=>0.0,'OFFNET'=>0.0];
                $combined['effective'][$service] -= min($partPools);
                foreach ($partPools as $scope => $quantity) {
                    $pools[$service][$scope] += $quantity;
                    if ($quantity > 0) $networkCoverage[$service][$scope] = max($networkCoverage[$service][$scope], $candidate['network_coverage'][$service][$scope] ?? $candidate['coverage_days']);
                }
                if ($candidate['effective'][$service] > min($partPools)) $networkCoverage[$service]['ALL'] = max($networkCoverage[$service]['ALL'], $candidate['network_coverage'][$service]['ALL'] ?? $candidate['coverage_days']);
            }
            foreach ($coverage as $service => $_) {
                if ($candidate['effective'][$service] > 0 || ($service === 'social' && $candidate['effective']['data'] > 0) || array_sum($candidate['network_pools'][$service] ?? []) > 0) $coverage[$service] = max($coverage[$service], $candidate['coverage_days']);
            }
            $requires = $candidate['plan']['rules']['requires_family'] ?? '';
            $resolved = $requires !== '' && ($familyCoverage[$requires] ?? 0) >= max(1, $profile['duration_days']);
            foreach (array_intersect($candidate['plan']['rules']['incompatible_families'] ?? [], $families) as $family) {
                $combined['conditional_requirements'][] = 'A combinação contém a família incompatível ' . $family . '.';
                $combined['restrictions'][] = 'A combinação contém a família incompatível ' . $family . '.';
            }
            foreach ($candidate['conditional_requirements'] as $requirement) {
                if ($resolved && str_starts_with($requirement, 'Requer um plano ' . $requires . ' activo')) continue;
                $combined['conditional_requirements'][] = $requirement;
            }
            foreach ($candidate['restrictions'] as $notice) {
                if ($resolved && str_starts_with($notice, 'Condição para activar: Requer um plano ' . $requires . ' activo')) continue;
                $combined['restrictions'][] = $candidate['title'] . ': ' . $notice;
            }
        }
        foreach ($pools as $service => $pool) $combined['effective'][$service] += min($pool);
        $combined['network_pools'] = $pools;
        $combined['title'] = implode(' + ', $titles);
        $keys = [];
        foreach ($combined['components'] as $part) {
            $item = $part['plan'] ?? $part['tariff'];
            $keys[] = (isset($part['plan']) ? 'plan:' : 'balance:') . (int) $item['id'] . ':' . (int) ($item['version_id'] ?? 0) . ':' . $part['cycles'] . ':' . number_format((float) $part['cost'],2,'.','');
        }
        sort($keys);
        $combined['result_key'] = 'combination:' . hash('sha256', implode('|', $keys));
        $combined['cycles'] = array_sum(array_column($combined['components'], 'cycles'));
        $combined['capacity'] = $this->allocateData($combined['effective'], $profile);
        foreach (['voice','sms'] as $service) {
            if (isset($coverage[$service]) && $profile[$service === 'sms' ? 'sms_scope' : 'call_scope'] === 'mixed') $coverage[$service] = max($networkCoverage[$service]['ALL'], min($networkCoverage[$service]['ONNET'],$networkCoverage[$service]['OFFNET']));
        }
        $combined['network_coverage'] = $networkCoverage;
        $combined['coverage_days'] = $coverage === [] ? 0 : min($coverage);
        $combined['coverage_ratio'] = $profile['duration_days'] > 0 ? min(1.0, $combined['coverage_days'] / $profile['duration_days']) : 1.0;
        $combined['conditional'] = $combined['conditional_requirements'] !== [];
        $combined['restrictions'][] = 'Combinação de até dois tipos de pacote, com um plano base adicional quando exigido, e saldo normal, na mesma operadora. Compre cada componente nos momentos indicados; benefícios não utilizados expiram com a sua própria validade.';
        return $combined;
    }

    private function applySharedWallet(
        array $group,
        int $cycles,
        array $profile,
        array &$nominal,
        array &$effective,
        array &$restrictions,
        array &$notices,
        array &$networkPools
    ): void {
        $eligible = [];
        $pool = 0.0;
        $description = null;
        $scopes = [];

        foreach ($group as $benefit) {
            $service = $this->serviceKey((string) ($benefit['type'] ?? ''), (string) ($benefit['unit'] ?? ''));
            if ($service === null) continue;

            $quantity = (float) ($benefit['quantity'] ?? 0) * $cycles;
            $pool = max($pool, $quantity);
            $description ??= $this->cleanSharedLabel((string) ($benefit['label'] ?? ''));

            $timeFactor = $this->timeFactor($benefit['start_time'] ?? null, $benefit['end_time'] ?? null, $profile['schedule']);
            $networkFactor = in_array($service, ['voice','sms'], true)
                ? $this->networkFactor(strtoupper((string) ($benefit['network_scope'] ?? 'ALL')), $profile[$service === 'sms' ? 'sms_scope' : 'call_scope'])
                : 1.0;

            foreach ($this->restrictionLabels($benefit, $service, $quantity, $profile, $timeFactor, $networkFactor) as $label) {
                $restrictions[$label] = true;
            }

            if ($timeFactor > 0 && ($networkFactor > 0 || (in_array($service, ['voice','sms'], true) && $profile[$service === 'sms' ? 'sms_scope' : 'call_scope'] === 'mixed')) && in_array($service, $profile['services'], true)) {
                $eligible[$service] = true;
                $scopes[$service] = strtoupper((string) ($benefit['network_scope'] ?? 'ALL'));
            }
        }

        if ($pool <= 0) return;

        $services = array_keys($eligible);
        $description = $description ?: 'Unidades partilhadas entre serviços';
        $notices[$this->formatNumber($pool) . " unidades são uma única carteira partilhada ({$description}); o QualPacote não conta o mesmo benefício duas vezes."] = true;
        if ($services === []) return;

        $targets = $this->minimumTargets($profile);
        $allocation = array_fill_keys($services, 0.0);
        $remaining = $pool;

        foreach ($services as $service) {
            $target = max(0.0, (float) ($targets[$service] ?? 0));
            if ($service === 'voice' && $profile['voice_mode'] === 'calls' && ($profile['voice_step_seconds'] ?? 0) > 0) {
                $seconds = $profile['avg_call_minutes'] * 60;
                $target *= ceil($seconds / $profile['voice_step_seconds']) * $profile['voice_step_seconds'] / $seconds;
            }
            if ($target <= 0) continue;
            $use = min($target, $remaining);
            $allocation[$service] += $use;
            $remaining -= $use;
            if ($remaining <= 0) break;
        }

        if ($remaining > 0) {
            $maxServices = array_values(array_filter($services, fn ($service) => $this->isMaximizeService($profile, $service)));
            if ($maxServices === []) $maxServices = $services;
            $weights = $this->serviceWeights($maxServices, $profile['priority']);
            foreach ($maxServices as $service) {
                $allocation[$service] += $remaining * ($weights[$service] ?? 0.0);
            }
        }

        foreach ($allocation as $service => $quantity) {
            $nominal[$service] += $quantity;
            $scope = $scopes[$service] ?? 'ALL';
            if (isset($networkPools[$service][$scope]) && $profile[$service === 'sms' ? 'sms_scope' : 'call_scope'] === 'mixed') $networkPools[$service][$scope] += $quantity;
            else $effective[$service] += $quantity;
        }
    }

    private function balanceCandidate(array $tariff, array $profile): ?array
    {
        $budget = $profile['budget_kz'];
        if ($budget <= 0 || empty($tariff['rates'])) return null;

        $rates = [];
        foreach ($profile['services'] as $service) {
            $rate = $this->effectiveTariffRate($tariff['rates'], $service, $profile);
            if ($rate !== null && $rate > 0) $rates[$service] = $rate;
        }
        if ($rates === []) return null;

        $targets = $this->minimumTargets($profile);
        $allocation = array_fill_keys(self::SERVICES, 0.0);
        $remaining = $budget;

        $requiredCost = 0.0;
        foreach ($targets as $service => $target) {
            if ($target > 0 && isset($rates[$service])) $requiredCost += $target * $rates[$service];
        }

        if ($requiredCost > 0 && $requiredCost <= $budget) {
            foreach ($targets as $service => $target) {
                if ($target <= 0 || !isset($rates[$service])) continue;
                $cost = $target * $rates[$service];
                $allocation[$service] += $cost;
                $remaining -= $cost;
            }
        }

        $eligibleForRemainder = [];
        foreach ($rates as $service => $_rate) {
            if ($this->isMaximizeService($profile, $service) || $requiredCost === 0 || $requiredCost > $budget) {
                $eligibleForRemainder[] = $service;
            }
        }
        if ($eligibleForRemainder === []) $eligibleForRemainder = array_keys($rates);

        $remainingWeights = $this->serviceWeights($eligibleForRemainder, $profile['priority']);
        foreach ($eligibleForRemainder as $service) {
            $allocation[$service] += max(0.0, $remaining) * ($remainingWeights[$service] ?? 0);
        }

        $capacity = array_fill_keys(self::SERVICES, 0.0);
        $potentials = [];
        foreach ($rates as $service => $rate) {
            $capacity[$service] = $allocation[$service] / $rate;
            $potentials[$service] = $budget / $rate;
        }

        if (isset($capacity['data']) && in_array('social', $profile['services'], true) && !isset($rates['social'])) {
            $capacity['social'] = $capacity['data'];
            $potentials['social'] = $potentials['data'] ?? 0;
        }

        $validity = isset($tariff['balance_validity_days']) && $tariff['balance_validity_days'] !== null
            ? (int) $tariff['balance_validity_days'] : 0;
        $duration = $profile['duration_days'];
        $coverageRatio = $duration === 0 ? 1.0 : ($validity === 0 ? 0.0 : min(1.0, $validity / max(1, $duration)));
        $coverageDays = $duration === 0 ? $validity : ($validity === 0 ? 0 : min($duration, $validity));

        $restrictions = [];
        if ($validity === 0 && $duration > 0) $restrictions['A validade do saldo não está confirmada; não podemos garantir o período pedido.'] = true;
        if ($profile['call_scope'] === 'mixed' || $profile['sms_scope'] === 'mixed') $restrictions['Para várias redes, usamos a tarifa mais elevada entre os destinos confirmados; o custo real pode ser inferior.'] = true;
        if ($profile['schedule'] === 'mixed') $restrictions['Para dia e noite, usamos a tarifa mais elevada dos horários confirmados.'] = true;
        $uncertain = $this->freshnessNotices($tariff, $restrictions);
        foreach (['sms'] as $service) $capacity[$service] = floor($capacity[$service]);
        return [
            'kind' => 'balance',
            'plan' => null,
            'tariff' => $tariff,
            'operator_id' => (int) ($tariff['operator_id'] ?? 0),
            'operator_name' => (string) ($tariff['operator_name'] ?? ''),
            'title' => 'Saldo normal — ' . (string) ($tariff['name'] ?? 'Tarifa base'),
            'effective_cost' => $budget,
            'unit_price' => $budget,
            'cycles' => 1,
            'coverage_days' => $coverageDays,
            'coverage_ratio' => $coverageRatio,
            'capacity' => $capacity,
            'nominal' => $capacity,
            'effective' => $capacity,
            'restrictions' => array_keys($restrictions),
            'notices' => [],
            'conditional_requirements' => $uncertain ? ['Tarifa precisa de nova confirmação.'] : [],
            'conditional' => $uncertain,
            'equivalent_balance_kz' => $budget,
            'equivalent_balance_complete' => true,
            'equivalent_tariff_name' => (string) ($tariff['name'] ?? ''),
            'balance_potentials' => $potentials,
        ];
    }

    private function assess(array $candidate, array $profile, array $maxima): array
    {
        $targets = $this->minimumTargets($profile);
        $minimumScores = [];
        $maximizeScores = [];
        $serviceWeights = $this->serviceWeights($profile['services'], $profile['priority']);

        foreach ($profile['services'] as $service) {
            $capacity = (float) ($candidate['capacity'][$service] ?? 0);
            $target = (float) ($targets[$service] ?? 0);
            if ($target > 0) $minimumScores[$service] = min(1.0, $capacity / $target);
            if ($this->isMaximizeService($profile, $service)) {
                $maximizeScores[$service] = ($maxima[$service] ?? 0) > 0
                    ? min(1.0, $capacity / $maxima[$service]) : 0.0;
            }
        }

        $minimumFit = $this->weightedAverage($minimumScores, $serviceWeights, 1.0);
        $maximizeFit = $this->weightedAverage($maximizeScores, $serviceWeights, 1.0);
        $coverageFit = (float) $candidate['coverage_ratio'];

        $requirementsMet = $coverageFit >= 0.999 && empty($candidate['conditional']);
        foreach ($profile['services'] as $service) if (($candidate['capacity'][$service] ?? 0) <= 0) $requirementsMet = false;
        foreach ($minimumScores as $score) {
            if ($score < 0.999) {
                $requirementsMet = false;
                break;
            }
        }

        $score = ($minimumFit * 0.50) + ($maximizeFit * 0.34) + ($coverageFit * 0.16);
        if (!empty($candidate['conditional'])) $score *= 0.82;

        return [
            'score' => round($score * 100, 2),
            'meets_requirements' => $requirementsMet,
            'minimum_fit' => round($minimumFit * 100),
            'maximize_fit' => round($maximizeFit * 100),
            'summary' => $this->summary($candidate, $requirementsMet, $minimumFit, $maximizeFit),
        ];
    }

    private function minimumTargets(array $profile): array
    {
        return [
            'data' => in_array('data', $profile['services'], true) && $profile['data_mode'] === 'minimum' ? $profile['data_mb'] : 0.0,
            'voice' => in_array('voice', $profile['services'], true) && in_array($profile['voice_mode'], ['minutes', 'calls'], true) ? $profile['voice_min'] : 0.0,
            'sms' => in_array('sms', $profile['services'], true) && $profile['sms_mode'] === 'minimum' ? $profile['sms'] : 0.0,
            'social' => in_array('social', $profile['services'], true) && $profile['social_mode'] === 'minimum' ? $profile['social_mb'] : 0.0,
        ];
    }

    private function isMaximizeService(array $profile, string $service): bool
    {
        return match ($service) {
            'data' => $profile['data_mode'] === 'max',
            'voice' => $profile['voice_mode'] === 'max',
            'sms' => $profile['sms_mode'] === 'max',
            'social' => $profile['social_mode'] === 'max',
            default => false,
        };
    }

    private function serviceWeights(array $services, string $priority): array
    {
        $services = array_values(array_unique($services));
        if ($services === []) return [];
        if (count($services) === 1) return [$services[0] => 1.0];

        $weights = [];
        if ($priority !== 'balanced' && in_array($priority, $services, true)) {
            $rest = 0.40 / (count($services) - 1);
            foreach ($services as $service) $weights[$service] = $service === $priority ? 0.60 : $rest;
            return $weights;
        }

        $equal = 1.0 / count($services);
        foreach ($services as $service) $weights[$service] = $equal;
        return $weights;
    }

    private function weightedAverage(array $scores, array $weights, float $default): float
    {
        if ($scores === []) return $default;
        $sum = 0.0;
        $weightSum = 0.0;
        foreach ($scores as $service => $score) {
            $weight = $weights[$service] ?? 0.0;
            if ($weight <= 0) $weight = 1.0;
            $sum += $score * $weight;
            $weightSum += $weight;
        }
        return $weightSum > 0 ? $sum / $weightSum : $default;
    }

    private function equivalentBalanceValue(array $plan, array $effective, array $profile, array $tariffs): array
    {
        $operatorId = (int) ($plan['operator_id'] ?? 0);
        $operatorTariffs = array_values(array_filter(
            $tariffs,
            static fn ($tariff) => (int) ($tariff['operator_id'] ?? 0) === $operatorId
        ));
        if ($operatorTariffs === []) return [null, false, null];

        usort($operatorTariffs, static fn ($a, $b) => ((int) ($b['is_default'] ?? 0)) <=> ((int) ($a['is_default'] ?? 0)));
        $value = 0.0;
        $hasBenefit = false;
        $complete = true;
        $usedTariffs = [];

        foreach (self::SERVICES as $service) {
            $quantity = (float) ($effective[$service] ?? 0);
            if ($quantity <= 0) continue;
            $hasBenefit = true;
            $rateService = $service === 'social' ? 'data' : $service;
            $rate = null;
            foreach ($operatorTariffs as $tariff) {
                $candidateRate = $this->effectiveTariffRate($tariff['rates'] ?? [], $rateService, $profile);
                if ($candidateRate === null) continue;
                $rate = $candidateRate;
                $usedTariffs[(string) ($tariff['name'] ?? 'Tarifa base')] = true;
                break;
            }
            if ($rate === null) {
                $complete = false;
                continue;
            }
            $value += $quantity * $rate;
        }

        return [
            $hasBenefit && $value > 0 ? round($value, 2) : null,
            $hasBenefit && $complete,
            implode(' + ', array_keys($usedTariffs)),
        ];
    }

    private function effectiveTariffRate(array $rates, string $service, array $profile): ?float
    {
        $type = match ($service) {
            'data', 'social' => 'DATA',
            'voice' => 'VOICE',
            'sms' => 'SMS',
            default => '',
        };
        if ($type === '') return null;

        $matching = array_values(array_filter(
            $rates,
            static fn ($rate) => strtoupper((string) ($rate['service_type'] ?? '')) === $type
        ));
        if ($matching === []) return null;
        if ($service === 'voice' && $profile['voice_mode'] === 'calls') {
            foreach ($matching as &$rate) {
                if (preg_match('/^BILLING_STEP_SECONDS:(1|60)\|/', (string) ($rate['label'] ?? ''), $m)) {
                    $physical = $profile['avg_call_minutes'] * 60;
                    $rate['price_kz_per_unit'] *= ceil($physical / (int) $m[1]) * (int) $m[1] / $physical;
                }
            }
            unset($rate);
        }

        if (!in_array($service, ['voice','sms'], true)) {
            $timed = $this->ratesForSchedule($matching, $profile['schedule']);
            return $this->averagePrice($timed);
        }

        $scope = $profile[$service === 'sms' ? 'sms_scope' : 'call_scope'];
        if ($scope === 'same') {
            $network = $this->ratesForNetwork($matching, ['ONNET', 'ALL']);
            return $this->scheduleAwarePrice($network, $profile['schedule']);
        }
        if ($scope === 'other') {
            $network = $this->ratesForNetwork($matching, ['OFFNET', 'ALL']);
            return $this->scheduleAwarePrice($network, $profile['schedule']);
        }

        $all = $this->ratesForNetwork($matching, ['ALL']);
        if ($all !== []) return $this->scheduleAwarePrice($all, $profile['schedule']);

        $on = $this->ratesForNetwork($matching, ['ONNET']);
        $off = $this->ratesForNetwork($matching, ['OFFNET']);
        if ($on !== [] && $off !== []) {
            $onPrice = $this->scheduleAwarePrice($on, $profile['schedule']);
            $offPrice = $this->scheduleAwarePrice($off, $profile['schedule']);
            if ($onPrice !== null && $offPrice !== null) return max($onPrice, $offPrice);
        }

        return null;
    }

    private function ratesForNetwork(array $rates, array $scopes): array
    {
        foreach ($scopes as $scope) {
            $filtered = array_values(array_filter(
                $rates,
                static fn ($rate) => strtoupper((string) ($rate['network_scope'] ?? 'ALL')) === $scope
            ));
            if ($filtered !== []) return $filtered;
        }
        return [];
    }

    private function scheduleAwarePrice(array $rates, string $schedule): ?float
    {
        if ($rates === []) return null;
        $filtered = $this->ratesForSchedule($rates, $schedule);
        return $this->averagePrice($filtered);
    }

    private function ratesForSchedule(array $rates, string $schedule): array
    {
        $untimed = array_values(array_filter($rates, static fn ($rate) => empty($rate['start_time']) || empty($rate['end_time'])));
        $timed = array_values(array_filter($rates, static fn ($rate) => !empty($rate['start_time']) && !empty($rate['end_time'])));
        if ($timed === []) return $untimed;

        $moments = match ($schedule) {
            'night' => [1260],
            'late_night' => [120],
            'mixed' => [720, 120],
            default => [720],
        };

        $selected = [];
        foreach ($moments as $minute) {
            $matches = array_values(array_filter(
                $timed,
                fn ($rate) => $this->timeContains((string) $rate['start_time'], (string) $rate['end_time'], $minute)
            ));
            if ($matches !== []) $selected = array_merge($selected, $matches);
            elseif ($untimed !== []) $selected = array_merge($selected, $untimed);
            else return [];
        }
        return $selected;
    }

    private function averagePrice(array $rates): ?float
    {
        if ($rates === []) return null;
        $prices = [];
        foreach ($rates as $rate) {
            $price = (float) ($rate['price_kz_per_unit'] ?? 0);
            if ($price > 0) $prices[] = $price;
        }
        return $prices === [] ? null : max($prices);
    }

    private function timeContains(string $start, string $end, int $minute): bool
    {
        $s = $this->timeToMinutes($start);
        $e = $this->timeToMinutes($end);
        if ($s <= $e) return $minute >= $s && $minute <= $e;
        return $minute >= $s || $minute <= $e;
    }

    private function serviceKey(string $type, string $unit): ?string
    {
        $type = strtoupper($type);
        $unit = strtoupper($unit);
        if ($type === 'DATA' && $unit === 'MB') return 'data';
        if ($type === 'SOCIAL' && $unit === 'MB') return 'social';
        if ($type === 'VOICE' && $unit === 'MIN') return 'voice';
        if ($type === 'SMS' && $unit === 'SMS') return 'sms';
        return null;
    }

    private function timeFactor(?string $start, ?string $end, string $schedule): float
    {
        if (!$start || !$end) return 1.0;
        if ($schedule === 'mixed') return 1.0;
        $probe = match ($schedule) { 'night'=>1260, 'late_night'=>120, default=>720 };
        return $this->timeContains($start, $end, $probe) ? 1.0 : 0.0;
    }

    private function networkFactor(string $scope, string $callScope): float
    {
        if ($scope === 'ALL') return 1.0;
        if ($callScope === 'mixed') return 0.0;
        if ($scope === 'ONNET') return $callScope === 'same' ? 1.0 : 0.0;
        if ($scope === 'OFFNET') return $callScope === 'other' ? 1.0 : 0.0;
        return 1.0;
    }

    private function restrictionLabels(
        array $benefit,
        string $service,
        float $quantity,
        array $profile,
        float $timeFactor,
        float $networkFactor
    ): array {
        $labels = [];
        $qty = $this->quantityLabel($service, $quantity);

        if ($timeFactor === 0.0 && !empty($benefit['start_time']) && !empty($benefit['end_time'])) {
            $start = substr((string) $benefit['start_time'], 0, 5);
            $end = substr((string) $benefit['end_time'], 0, 5);
            if ($profile['schedule'] === 'day') {
                $labels[] = "{$qty} deste benefício só ficam disponíveis das {$start} às {$end}. Como indicou uso diurno, estes {$qty} ficam totalmente fora do cálculo útil; num perfil diurno, é provável que esteja a dormir durante boa parte desse horário.";
            } elseif (in_array($profile['schedule'], ['night','late_night'], true)) {
                $labels[] = "{$qty} deste benefício só ficam disponíveis das {$start} às {$end}. Como indicou uso principalmente à noite/madrugada, estes {$qty} ficam totalmente fora do horário que escolheu.";
            }
        }

        if ($networkFactor === 0.0 && in_array($service, ['voice','sms'], true)) {
            $scope = strtoupper((string) ($benefit['network_scope'] ?? 'ALL'));
            $action = $service === 'sms' ? 'envia SMS' : 'liga';
            if ($scope === 'ONNET' && $profile[$service === 'sms' ? 'sms_scope' : 'call_scope'] === 'other') {
                $labels[] = "{$qty} são exclusivos para a mesma rede. Como indicou que {$action} principalmente para outras redes, estes {$qty} não entram no cálculo útil.";
            } elseif ($scope === 'OFFNET' && $profile[$service === 'sms' ? 'sms_scope' : 'call_scope'] === 'same') {
                $labels[] = "{$qty} são exclusivos para outras redes. Como indicou que {$action} principalmente dentro da mesma rede, estes {$qty} não entram no cálculo útil.";
            }
        }

        return $labels;
    }

    private function sharedWalletKey(array $benefit): ?string
    {
        $label = trim((string) ($benefit['label'] ?? ''));
        if (preg_match('/^SHARED:([a-z0-9_-]+)\|/i', $label, $m)) return strtolower($m[1]);
        return null;
    }

    private function cleanSharedLabel(string $label): string
    {
        return trim((string) preg_replace('/^SHARED:[a-z0-9_-]+\|/i', '', $label));
    }

    private function dailyCapMb(array $benefit): ?float
    {
        $label = trim((string) ($benefit['label'] ?? ''));
        if (preg_match('/^DAILY_(?:CAP|RELEASE):([0-9]+(?:\.[0-9]+)?)\|/i', $label, $m)) {
            return max(0.0, (float) $m[1]);
        }
        return null;
    }

    private function taggedLines(string $notes, string $tag): array
    {
        if ($notes === '') return [];
        if (!preg_match('/\[' . preg_quote($tag, '/') . '\](.*?)\[\/' . preg_quote($tag, '/') . '\]/si', $notes, $m)) {
            return [];
        }
        return array_values(array_filter(array_map('trim', preg_split('/\R+/', trim($m[1])) ?: [])));
    }

    private function quantityLabel(string $service, float $quantity): string
    {
        if (in_array($service, ['data', 'social'], true)) {
            if ($quantity >= 1024) return $this->formatNumber($quantity / 1024) . ' GB';
            return $this->formatNumber($quantity) . ' MB';
        }
        if ($service === 'voice') return $this->formatNumber($quantity) . ' min';
        if ($service === 'sms') return $this->formatNumber($quantity) . ' SMS';
        return $this->formatNumber($quantity);
    }

    private function formatNumber(float $value): string
    {
        $decimals = abs($value - round($value)) < 0.001 ? 0 : 1;
        return number_format($value, $decimals, ',', '.');
    }

    private function timeToMinutes(string $time): int
    {
        [$h, $m] = array_pad(array_map('intval', explode(':', $time)), 2, 0);
        return ($h * 60) + $m;
    }

    private function summary(array $candidate, bool $meets, float $minimumFit, float $maximizeFit): string
    {
        if (!empty($candidate['conditional'])) {
            return 'Pode ser vantajoso, mas depende de uma condição adicional indicada abaixo.';
        }

        if (!$meets) {
            if ((float) $candidate['coverage_ratio'] < 0.999) return 'Cabe no orçamento, mas não dura todo o período que indicou.';
            if ($minimumFit < 0.999) return 'É uma alternativa dentro do orçamento, mas fica abaixo de um mínimo que indicou.';
        }

        if ($candidate['kind'] === 'balance') {
            return 'Mantém o valor flexível: paga apenas o que consumir, segundo a tarifa normal desta opção.';
        }

        if ($maximizeFit >= 0.95 && $minimumFit >= 0.999) return 'Entrega muito do que pediu dentro do orçamento e do período escolhido.';
        if ($minimumFit >= 0.999) return 'Cumpre os mínimos que indicou e mantém-se dentro do orçamento.';
        return 'É uma das alternativas mais próximas do que pediu para este orçamento.';
    }
}
