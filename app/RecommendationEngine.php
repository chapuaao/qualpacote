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

        foreach ($plans as $plan) {
            $candidate = $this->planCandidate($plan, $profile, $tariffs);
            if ($candidate !== null) {
                $candidates[] = $candidate;
            }
        }

        foreach ($tariffs as $tariff) {
            $candidate = $this->balanceCandidate($tariff, $profile);
            if ($candidate !== null) {
                $candidates[] = $candidate;
            }
        }

        if ($candidates === []) {
            return [];
        }

        $maxima = array_fill_keys(self::SERVICES, 0.0);
        foreach ($candidates as $candidate) {
            foreach (self::SERVICES as $service) {
                $maxima[$service] = max($maxima[$service], (float) ($candidate['capacity'][$service] ?? 0));
            }
        }

        foreach ($candidates as &$candidate) {
            $assessment = $this->assess($candidate, $profile, $maxima);
            $candidate = array_merge($candidate, $assessment);
        }
        unset($candidate);

        usort($candidates, static function (array $a, array $b): int {
            $meets = ((int) $b['meets_requirements']) <=> ((int) $a['meets_requirements']);
            if ($meets !== 0) return $meets;

            $score = $b['score'] <=> $a['score'];
            if ($score !== 0) return $score;

            $cost = $a['effective_cost'] <=> $b['effective_cost'];
            if ($cost !== 0) return $cost;

            return strcmp((string) $a['title'], (string) $b['title']);
        });

        return array_slice($candidates, 0, 8);
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
        if ($priority !== 'balanced' && !in_array($priority, $services, true)) {
            $priority = 'balanced';
        }

        $duration = (int) ($profile['duration_days'] ?? 30);
        $duration = max(0, min(90, $duration));

        $schedule = (string) ($profile['schedule'] ?? 'day');
        if (!in_array($schedule, ['day', 'mixed', 'night'], true)) $schedule = 'day';

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
            $average = max(1.0, min(30.0, (float) ($profile['avg_call_minutes'] ?? 3)));
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
        ];
    }

    private function planCandidate(array $plan, array $profile, array $tariffs): ?array
    {
        $price = (float) ($plan['price_kz'] ?? 0);
        $budget = $profile['budget_kz'];
        if ($price <= 0 || $price > $budget) return null;

        $validity = max(1, (int) ($plan['validity_days'] ?? 1));
        $duration = $profile['duration_days'];
        $requiredCycles = $duration > 0 ? (int) ceil($duration / $validity) : 1;
        $cycles = max(1, min($requiredCycles, (int) floor($budget / $price)));
        $effectiveCost = $price * $cycles;
        $coverageDays = $duration > 0 ? min($duration, $validity * $cycles) : $validity;
        $coverageRatio = $duration > 0 ? min(1.0, $coverageDays / $duration) : 1.0;

        $nominal = array_fill_keys(self::SERVICES, 0.0);
        $effective = array_fill_keys(self::SERVICES, 0.0);
        $restrictions = [];

        foreach ($plan['benefits'] ?? [] as $benefit) {
            $quantity = (float) ($benefit['quantity'] ?? 0);
            if ($quantity <= 0) continue;

            $service = $this->serviceKey((string) ($benefit['type'] ?? ''), (string) ($benefit['unit'] ?? ''));
            if ($service === null) continue;

            $timeFactor = $this->timeFactor($benefit['start_time'] ?? null, $benefit['end_time'] ?? null, $profile['schedule']);
            $networkFactor = $service === 'voice'
                ? $this->networkFactor(strtoupper((string) ($benefit['network_scope'] ?? 'ALL')), $profile['call_scope'])
                : 1.0;
            $factor = $timeFactor * $networkFactor;

            $nominal[$service] += $quantity * $cycles;
            $effective[$service] += $quantity * $factor * $cycles;

            if ($factor < 0.95 || !empty($benefit['app_scope'])) {
                $label = $this->restrictionLabel($benefit, $factor);
                if ($label !== '') $restrictions[$label] = true;
            }
        }

        $capacity = $effective;
        $capacity['social'] = $effective['social'] + $effective['data'];

        [$equivalent, $equivalentComplete, $equivalentTariff] = $this->equivalentBalanceValue($plan, $effective, $profile, $tariffs);

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
            'equivalent_balance_kz' => $equivalent,
            'equivalent_balance_complete' => $equivalentComplete,
            'equivalent_tariff_name' => $equivalentTariff,
            'balance_potentials' => [],
        ];
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
            ? (int) $tariff['balance_validity_days']
            : 0;
        $duration = $profile['duration_days'];
        $coverageRatio = ($duration === 0 || $validity === 0) ? 1.0 : min(1.0, $validity / max(1, $duration));
        $coverageDays = $duration === 0 ? $validity : ($validity === 0 ? $duration : min($duration, $validity));

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
            'restrictions' => [],
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
            if ($target > 0) {
                $minimumScores[$service] = min(1.0, $capacity / $target);
            }
            if ($this->isMaximizeService($profile, $service)) {
                $maximizeScores[$service] = ($maxima[$service] ?? 0) > 0
                    ? min(1.0, $capacity / $maxima[$service])
                    : 0.0;
            }
        }

        $minimumFit = $this->weightedAverage($minimumScores, $serviceWeights, 1.0);
        $maximizeFit = $this->weightedAverage($maximizeScores, $serviceWeights, 1.0);
        $coverageFit = (float) $candidate['coverage_ratio'];

        $requirementsMet = $coverageFit >= 0.999;
        foreach ($minimumScores as $score) {
            if ($score < 0.999) {
                $requirementsMet = false;
                break;
            }
        }

        $restrictionFactor = 1.0 - min(0.25, count($candidate['restrictions']) * 0.04);
        $score = (($minimumFit * 0.50) + ($maximizeFit * 0.34) + ($coverageFit * 0.16)) * $restrictionFactor;

        return [
            'score' => round($score * 100, 2),
            'meets_requirements' => $requirementsMet,
            'minimum_fit' => round($minimumFit * 100),
            'maximize_fit' => round($maximizeFit * 100),
            'summary' => $this->summary($candidate, $profile, $requirementsMet, $minimumFit, $maximizeFit),
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
        $operatorTariffs = array_values(array_filter($tariffs, static fn ($tariff) => (int) ($tariff['operator_id'] ?? 0) === $operatorId));
        if ($operatorTariffs === []) return [null, false, null];

        usort($operatorTariffs, static fn ($a, $b) => ((int) ($b['is_default'] ?? 0)) <=> ((int) ($a['is_default'] ?? 0)));
        $value = 0.0;
        $hasBenefit = false;
        $complete = true;
        $usedTariffs = [];

        foreach (['data', 'voice', 'sms', 'social'] as $service) {
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

        $matching = array_values(array_filter($rates, static fn ($rate) => strtoupper((string) ($rate['service_type'] ?? '')) === $type));
        if ($matching === []) return null;

        if ($service !== 'voice') {
            $timed = $this->ratesForSchedule($matching, $profile['schedule']);
            $chosen = $timed !== [] ? $timed : $matching;
            return $this->averagePrice($chosen);
        }

        $scope = $profile['call_scope'];
        if ($scope === 'same') {
            $network = $this->ratesForNetwork($matching, ['ONNET', 'ALL']);
            return $this->scheduleAwarePrice($network ?: $matching, $profile['schedule']);
        }
        if ($scope === 'other') {
            $network = $this->ratesForNetwork($matching, ['OFFNET', 'ALL']);
            return $this->scheduleAwarePrice($network ?: $matching, $profile['schedule']);
        }

        $all = $this->ratesForNetwork($matching, ['ALL']);
        if ($all !== []) return $this->scheduleAwarePrice($all, $profile['schedule']);

        $on = $this->ratesForNetwork($matching, ['ONNET']);
        $off = $this->ratesForNetwork($matching, ['OFFNET']);
        if ($on !== [] && $off !== []) {
            $onPrice = $this->scheduleAwarePrice($on, $profile['schedule']);
            $offPrice = $this->scheduleAwarePrice($off, $profile['schedule']);
            if ($onPrice !== null && $offPrice !== null) return ($onPrice + $offPrice) / 2;
        }

        return $this->scheduleAwarePrice($matching, $profile['schedule']);
    }

    private function ratesForNetwork(array $rates, array $scopes): array
    {
        foreach ($scopes as $scope) {
            $filtered = array_values(array_filter($rates, static fn ($rate) => strtoupper((string) ($rate['network_scope'] ?? 'ALL')) === $scope));
            if ($filtered !== []) return $filtered;
        }
        return [];
    }

    private function scheduleAwarePrice(array $rates, string $schedule): ?float
    {
        if ($rates === []) return null;
        $filtered = $this->ratesForSchedule($rates, $schedule);
        return $this->averagePrice($filtered !== [] ? $filtered : $rates);
    }

    private function ratesForSchedule(array $rates, string $schedule): array
    {
        $untimed = array_values(array_filter($rates, static fn ($rate) => empty($rate['start_time']) || empty($rate['end_time'])));
        $timed = array_values(array_filter($rates, static fn ($rate) => !empty($rate['start_time']) && !empty($rate['end_time'])));
        if ($timed === []) return $untimed;

        $moments = match ($schedule) {
            'night' => [120],
            'mixed' => [720, 120],
            default => [720],
        };

        $selected = [];
        foreach ($moments as $minute) {
            $matches = array_values(array_filter($timed, fn ($rate) => $this->timeContains((string) $rate['start_time'], (string) $rate['end_time'], $minute)));
            if ($matches !== []) $selected = array_merge($selected, $matches);
            elseif ($untimed !== []) $selected = array_merge($selected, $untimed);
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
        return $prices === [] ? null : array_sum($prices) / count($prices);
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
        $day = $this->timeContains($start, $end, 720) ? 1.0 : 0.05;
        $night = $this->timeContains($start, $end, 120) ? 1.0 : 0.05;
        return match ($schedule) {
            'night' => $night,
            'mixed' => ($day + $night) / 2,
            default => $day,
        };
    }

    private function networkFactor(string $scope, string $callScope): float
    {
        if ($scope === 'ALL') return 1.0;
        if ($scope === 'ONNET') return match ($callScope) {'same' => 1.0, 'other' => 0.05, default => 0.50};
        if ($scope === 'OFFNET') return match ($callScope) {'same' => 0.05, 'other' => 1.0, default => 0.50};
        return 0.8;
    }

    private function timeToMinutes(string $time): int
    {
        [$h, $m] = array_pad(array_map('intval', explode(':', $time)), 2, 0);
        return ($h * 60) + $m;
    }

    private function restrictionLabel(array $benefit, float $factor): string
    {
        $parts = [];
        if (!empty($benefit['start_time']) && !empty($benefit['end_time'])) {
            $parts[] = sprintf('Parte do benefício só vale das %s às %s', substr((string) $benefit['start_time'], 0, 5), substr((string) $benefit['end_time'], 0, 5));
        }
        $scope = strtoupper((string) ($benefit['network_scope'] ?? 'ALL'));
        if ($scope === 'ONNET') $parts[] = 'Parte das chamadas é apenas para a mesma rede';
        elseif ($scope === 'OFFNET') $parts[] = 'Parte das chamadas é apenas para outras redes';
        if (!empty($benefit['app_scope'])) $parts[] = 'Há dados exclusivos para aplicações específicas';
        if ($parts === [] && $factor < 0.95) return 'Há benefícios com utilização limitada';
        return implode('. ', $parts);
    }

    private function summary(array $candidate, array $profile, bool $meets, float $minimumFit, float $maximizeFit): string
    {
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
