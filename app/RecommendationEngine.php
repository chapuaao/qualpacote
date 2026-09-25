<?php
declare(strict_types=1);

namespace QualPacote;

final class RecommendationEngine
{
    public function recommend(array $plans, array $profile): array
    {
        $profile = $this->normalizeProfile($profile);
        $needs = $this->needs($profile);
        $weights = $this->weights($profile['goal']);

        $results = [];

        foreach ($plans as $plan) {
            $price = (float) $plan['price_kz'];
            $validity = max(1, (int) $plan['validity_days']);
            $budget = $profile['budget_kz'];

            if ($price <= 0 || $price > $budget) {
                continue;
            }

            $requiredCycles = (int) ceil($profile['duration_days'] / $validity);
            $affordableCycles = max(1, min($requiredCycles, (int) floor($budget / $price)));
            $effectiveCost = $price * $affordableCycles;
            $coverageDays = min($profile['duration_days'], $validity * $affordableCycles);
            $coverageRatio = min(1.0, $coverageDays / $profile['duration_days']);

            $nominal = ['data_mb' => 0.0, 'voice_min' => 0.0, 'sms' => 0.0, 'social_mb' => 0.0];
            $effective = ['data_mb' => 0.0, 'voice_min' => 0.0, 'sms' => 0.0, 'social_mb' => 0.0];
            $restrictions = [];

            foreach ($plan['benefits'] ?? [] as $benefit) {
                $quantity = (float) ($benefit['quantity'] ?? 0);
                if ($quantity <= 0) {
                    continue;
                }

                $type = strtoupper((string) ($benefit['type'] ?? ''));
                $unit = strtoupper((string) ($benefit['unit'] ?? ''));
                $key = $this->metricKey($type, $unit);

                if ($key === null) {
                    continue;
                }

                $timeFactor = $this->timeFactor(
                    $benefit['start_time'] ?? null,
                    $benefit['end_time'] ?? null,
                    $profile['schedule']
                );
                $networkFactor = $this->networkFactor(
                    strtoupper((string) ($benefit['network_scope'] ?? 'ALL')),
                    $profile['call_scope']
                );
                $factor = $timeFactor * $networkFactor;

                $nominal[$key] += $quantity * $affordableCycles;
                $effective[$key] += $quantity * $factor * $affordableCycles;

                if ($factor < 0.95) {
                    $restriction = $this->restrictionLabel($benefit, $factor);
                    if ($restriction !== '') {
                        $restrictions[$restriction] = true;
                    }
                }
            }

            $effectiveSocial = $effective['social_mb'] + $effective['data_mb'];
            $nominalSocial = $nominal['social_mb'] + $nominal['data_mb'];

            $fulfillment = [
                'data' => $needs['data_mb'] > 0 ? min(1.0, $effective['data_mb'] / $needs['data_mb']) : 1.0,
                'voice' => $needs['voice_min'] > 0 ? min(1.0, $effective['voice_min'] / $needs['voice_min']) : 1.0,
                'sms' => $needs['sms'] > 0 ? min(1.0, $effective['sms'] / $needs['sms']) : 1.0,
                'social' => $needs['social_mb'] > 0 ? min(1.0, $effectiveSocial / $needs['social_mb']) : 1.0,
            ];

            $fit = 0.0;
            foreach ($weights as $metric => $weight) {
                $fit += $fulfillment[$metric] * $weight;
            }

            $budgetEfficiency = max(0.0, 1.0 - ($effectiveCost / max(1.0, $budget)));
            $score = ($fit * 0.72) + ($coverageRatio * 0.20) + ($budgetEfficiency * 0.08);

            $restrictionLoss = $this->restrictionLoss($nominal, $effective, $nominalSocial, $effectiveSocial, $weights);

            $results[] = [
                'plan' => $plan,
                'score' => round($score * 100, 1),
                'fit_percent' => round($fit * 100),
                'coverage_percent' => round($coverageRatio * 100),
                'coverage_days' => $coverageDays,
                'effective_cost' => $effectiveCost,
                'required_cost' => $price * $requiredCycles,
                'cycles' => $affordableCycles,
                'effective' => $effective,
                'nominal' => $nominal,
                'restriction_loss_percent' => round($restrictionLoss * 100),
                'restrictions' => array_keys($restrictions),
                'summary' => $this->summary($profile, $needs, $effective, $coverageRatio),
            ];
        }

        usort($results, static function (array $a, array $b): int {
            $score = $b['score'] <=> $a['score'];
            if ($score !== 0) {
                return $score;
            }

            $cost = $a['effective_cost'] <=> $b['effective_cost'];
            if ($cost !== 0) {
                return $cost;
            }

            return $b['fit_percent'] <=> $a['fit_percent'];
        });

        return array_slice($results, 0, 5);
    }

    private function normalizeProfile(array $profile): array
    {
        $goal = (string) ($profile['goal'] ?? 'balanced');
        $usage = (string) ($profile['usage_level'] ?? 'normal');
        $schedule = (string) ($profile['schedule'] ?? 'day');
        $callScope = (string) ($profile['call_scope'] ?? 'mixed');

        return [
            'budget_kz' => max(0.0, (float) ($profile['budget_kz'] ?? 0)),
            'goal' => in_array($goal, ['data', 'voice', 'balanced', 'social'], true) ? $goal : 'balanced',
            'usage_level' => in_array($usage, ['light', 'normal', 'heavy'], true) ? $usage : 'normal',
            'duration_days' => max(1, min(90, (int) ($profile['duration_days'] ?? 30))),
            'schedule' => in_array($schedule, ['day', 'mixed', 'night'], true) ? $schedule : 'day',
            'call_scope' => in_array($callScope, ['same', 'mixed'], true) ? $callScope : 'mixed',
            'data_gb' => isset($profile['data_gb']) && $profile['data_gb'] !== '' ? max(0.0, (float) $profile['data_gb']) : null,
            'minutes' => isset($profile['minutes']) && $profile['minutes'] !== '' ? max(0.0, (float) $profile['minutes']) : null,
        ];
    }

    private function needs(array $profile): array
    {
        $presets = [
            'data' => [
                'light' => [1500, 40, 20],
                'normal' => [6000, 120, 30],
                'heavy' => [15000, 250, 40],
            ],
            'voice' => [
                'light' => [500, 120, 30],
                'normal' => [1500, 350, 60],
                'heavy' => [3000, 900, 100],
            ],
            'balanced' => [
                'light' => [1500, 120, 30],
                'normal' => [5000, 300, 60],
                'heavy' => [10000, 700, 100],
            ],
            'social' => [
                'light' => [2000, 40, 20],
                'normal' => [6000, 100, 30],
                'heavy' => [12000, 180, 50],
            ],
        ];

        [$dataMb, $voiceMin, $sms] = $presets[$profile['goal']][$profile['usage_level']];

        // Presets represent 30 days. Scale them to the period selected by the user.
        $periodFactor = $profile['duration_days'] / 30;
        $dataMb *= $periodFactor;
        $voiceMin *= $periodFactor;
        $sms *= $periodFactor;

        // Exact values entered by the user already refer to the selected period.
        if ($profile['data_gb'] !== null) {
            $dataMb = $profile['data_gb'] * 1024;
        }

        if ($profile['minutes'] !== null) {
            $voiceMin = $profile['minutes'];
        }

        return [
            'data_mb' => (float) $dataMb,
            'voice_min' => (float) $voiceMin,
            'sms' => (float) $sms,
            'social_mb' => $profile['goal'] === 'social' ? (float) $dataMb : 0.0,
        ];
    }

    private function weights(string $goal): array
    {
        return match ($goal) {
            'data' => ['data' => 0.70, 'voice' => 0.20, 'sms' => 0.10, 'social' => 0.00],
            'voice' => ['data' => 0.18, 'voice' => 0.72, 'sms' => 0.10, 'social' => 0.00],
            'social' => ['data' => 0.20, 'voice' => 0.10, 'sms' => 0.00, 'social' => 0.70],
            default => ['data' => 0.45, 'voice' => 0.45, 'sms' => 0.10, 'social' => 0.00],
        };
    }

    private function metricKey(string $type, string $unit): ?string
    {
        if ($type === 'DATA' && $unit === 'MB') return 'data_mb';
        if ($type === 'SOCIAL' && $unit === 'MB') return 'social_mb';
        if ($type === 'VOICE' && $unit === 'MIN') return 'voice_min';
        if ($type === 'SMS' && $unit === 'SMS') return 'sms';
        return null;
    }

    private function timeFactor(?string $start, ?string $end, string $schedule): float
    {
        if (!$start || !$end) return 1.0;

        $startMinutes = $this->timeToMinutes($start);
        $endMinutes = $this->timeToMinutes($end);

        if ($startMinutes <= 60 && $endMinutes <= 480) {
            return match ($schedule) {'night' => 0.90, 'mixed' => 0.28, default => 0.05};
        }
        if ($startMinutes >= 360 && $endMinutes >= 1200) {
            return match ($schedule) {'night' => 0.30, 'mixed' => 0.78, default => 0.95};
        }
        return match ($schedule) {'mixed' => 0.70, default => 0.60};
    }

    private function networkFactor(string $scope, string $callScope): float
    {
        if ($scope === 'ALL') return 1.0;
        if ($scope === 'ONNET') return $callScope === 'same' ? 0.95 : 0.55;
        if ($scope === 'OFFNET') return $callScope === 'same' ? 0.25 : 0.70;
        return 0.80;
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

    private function restrictionLoss(array $nominal, array $effective, float $nominalSocial, float $effectiveSocial, array $weights): float
    {
        $ratios = [
            'data' => $nominal['data_mb'] > 0 ? $effective['data_mb'] / $nominal['data_mb'] : 1.0,
            'voice' => $nominal['voice_min'] > 0 ? $effective['voice_min'] / $nominal['voice_min'] : 1.0,
            'sms' => $nominal['sms'] > 0 ? $effective['sms'] / $nominal['sms'] : 1.0,
            'social' => $nominalSocial > 0 ? $effectiveSocial / $nominalSocial : 1.0,
        ];
        $weighted = 0.0;
        foreach ($weights as $metric => $weight) $weighted += min(1.0, max(0.0, $ratios[$metric])) * $weight;
        return max(0.0, 1.0 - $weighted);
    }

    private function summary(array $profile, array $needs, array $effective, float $coverageRatio): string
    {
        if ($coverageRatio < 0.99) return 'Cabe no orçamento, mas não cobre todo o período escolhido.';

        $dataFit = $needs['data_mb'] > 0 ? $effective['data_mb'] / $needs['data_mb'] : 1.0;
        $voiceFit = $needs['voice_min'] > 0 ? $effective['voice_min'] / $needs['voice_min'] : 1.0;

        if ($profile['goal'] === 'data' && $dataFit >= 1) return 'Cobre bem a necessidade de internet dentro do orçamento.';
        if ($profile['goal'] === 'voice' && $voiceFit >= 1) return 'Cobre bem a necessidade de chamadas dentro do orçamento.';
        if ($dataFit >= 0.8 && $voiceFit >= 0.8) return 'Boa combinação de internet e chamadas para o perfil informado.';
        return 'É uma das opções que melhor aproveita o orçamento informado.';
    }
}
