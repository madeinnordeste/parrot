<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

const DEFAULT_MIN_GLUCOSE = 70;
const DEFAULT_MAX_GLUCOSE = 180;
const DEFAULT_DAYS = 14;
const TWIG_LAYOUT = 'layout.html.twig';
const DAY_PERIODS = [
    'dawn' => [0, 6],
    'morning' => [6, 12],
    'afternoon' => [12, 18],
    'night' => [18, 24]
];

use Nightstats\Nightstats;
use Dotenv\Dotenv;
use Parrot\Validation\InputValidator;
use HiFolks\Statistics\Statistics;

function buildFilters(array $post): array
{
    return [
        'nightscout_address' => $post['nightscout_address'] ?? '',
        'min_glucose' => (int)($post['min_glucose'] ?? DEFAULT_MIN_GLUCOSE),
        'max_glucose' => (int)($post['max_glucose'] ?? DEFAULT_MAX_GLUCOSE),
        'days' => (int)($post['days'] ?? DEFAULT_DAYS)
    ];
}

function buildError(string $title, string $message): array
{
    return ['title' => $title, 'message' => $message];
}

function render(\Twig\Environment $twig, array $data): void
{
    echo $twig->render(TWIG_LAYOUT, $data);
}

function buildBaseUrl(): string
{
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . '://' . $host;
}

function calculateDayPeriodStats(array $agpValues): array
{
    $dayPeriods = [];

    foreach (DAY_PERIODS as $period => [$periodStart, $periodEnd]) {
        $filteredByHour = array_filter(
            $agpValues,
        fn($_, $hour) => $hour >= $periodStart && $hour <= $periodEnd,
            ARRAY_FILTER_USE_BOTH
        );

        $periodAllValues = array_merge(...array_column($filteredByHour, 'values'));

        if (empty($periodAllValues)) {
            $dayPeriods[$period] = [
                'count' => 0, 'mean' => 0, 'sd' => 0, 'cv' => 0,
                'p25' => 0, 'p50' => 0, 'p75' => 0,
                'min' => 0, 'max' => 0, 'avg' => 0, 'values' => []
            ];
            continue;
        }

        $stats = Statistics::make($periodAllValues);
        $mean = $stats->mean();
        $sd = $stats->stdev();

        $dayPeriods[$period] = [
            'count' => count($periodAllValues),
            'mean' => $mean,
            'sd' => $sd,
            'cv' => $stats->coefficientOfVariation() ?? ($sd / $mean) * 100,
            'p25' => $stats->firstQuartile(),
            'p50' => $stats->median(),
            'p75' => $stats->thirdQuartile(),
            'min' => min($periodAllValues),
            'max' => max($periodAllValues),
            'avg' => array_sum($periodAllValues) / count($periodAllValues),
            'values' => $periodAllValues,
        ];
    }

    return $dayPeriods;
}

function validateAndProcess(): ?array
{
    global $recaptchaSecret;

    if (empty($_POST)) {
        return null;
    }

    $filters = buildFilters($_POST);
    $recaptchaResponse = $_POST['g-recaptcha-response'] ?? null;

    $validation = InputValidator::validateRecaptchaResponse($recaptchaResponse);
    if (!$validation->isValid()) {
        return ['error' => buildError($validation->getTitle(), $validation->getMessage()), 'filters' => $filters];
    }

    if (empty($recaptchaSecret)) {
        return ['error' => buildError('Configuration Error', 'Recaptcha is not configured'), 'filters' => $filters];
    }

    $recaptcha = new \ReCaptcha\ReCaptcha($recaptchaSecret);
    $recaptchaResult = $recaptcha->verify($recaptchaResponse);

    if (!$recaptchaResult->isSuccess()) {
        $errors = $recaptchaResult->getErrorCodes();
        return ['error' => buildError('Recaptcha Error', 'Error(s): ' . implode(', ', $errors)), 'filters' => $filters];
    }

    $validation = InputValidator::validateNightscoutAddress($filters['nightscout_address']);
    if (!$validation->isValid()) {
        return ['error' => buildError($validation->getTitle(), $validation->getMessage()), 'filters' => $filters];
    }

    $validation = InputValidator::validateGlucoseRange($filters['min_glucose'], $filters['max_glucose']);
    if (!$validation->isValid()) {
        return ['error' => buildError($validation->getTitle(), $validation->getMessage()), 'filters' => $filters];
    }

    $validation = InputValidator::validateDays($filters['days']);
    if (!$validation->isValid()) {
        return ['error' => buildError($validation->getTitle(), $validation->getMessage()), 'filters' => $filters];
    }

    try {
        $nightstats = new Nightstats(
            $filters['nightscout_address'],
            $filters['min_glucose'],
            $filters['max_glucose']
            );
        $stats = $nightstats->getStats($filters['days'], false);
        return ['stats' => $stats, 'filters' => $filters];
    }
    catch (Exception $e) {
        return ['error' => buildError('Error', $e->getMessage()), 'filters' => $filters];
    }
}

$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/assets/');
$twig = new \Twig\Environment($loader);

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

$recaptchaKey = $_ENV['RECAPTCHA_KEY'] ?? '';
$recaptchaSecret = $_ENV['RECAPTCHA_SECRET'] ?? '';

$baseUrl = buildBaseUrl();

$data = [
    'filters' => buildFilters($_POST),
    'recaptcha_key' => $recaptchaKey,
    'error' => null,
    'stats' => null,
    'base_url' => $baseUrl
];

$result = validateAndProcess();

if ($result !== null) {

    $agpValues = $result['stats']['glucose']['agp'] ?? [];
    $result['stats']['glucose']['daily_periods'] = calculateDayPeriodStats($agpValues);

    $data['filters'] = $result['filters'];
    $data['error'] = $result['error'] ?? null;
    $data['stats'] = $result['stats'] ?? null;
}

render($twig, $data);