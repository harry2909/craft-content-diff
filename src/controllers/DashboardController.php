<?php

namespace roadsterworks\craftcontentdiff\controllers;

use Craft;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use yii\web\Response;
use roadsterworks\craftcontentdiff\CraftContentDiff;

/**
 * Control panel dashboard: compare this site’s entries with staging or production.
 */
class DashboardController extends Controller
{
    private const ENV_PRODUCTION = 'production';
    private const ENV_STAGING = 'staging';
    private const ENV_DEV = 'dev';
    private const ENV_LOCAL = 'local';

    public $defaultAction = 'index';
    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

    private CraftContentDiff $plugin;

    /**
     * @inheritdoc
     */
    public function __construct($id, $module, array $config = [])
    {
        parent::__construct($id, $module, $config);
        $this->plugin = CraftContentDiff::getInstance();
    }

    /**
     * craft-content-diff/dashboard action.
     * ?compare=local|production|staging runs a compare and shows the result (local uses fake diff for testing).
     */
    public function actionIndex(): Response
    {
        $this->requireCpRequest();

        $currentEnv = $this->getCurrentEnvironment();
        $compareTargets = $this->getCompareTargetsForCurrentEnvironment($currentEnv);

        $compareResult = null;
        $compareLabel = null;
        $compareError = null;

        $compareEnv = Craft::$app->getRequest()->getQueryParam('compare');
        if (in_array($compareEnv, [self::ENV_LOCAL, self::ENV_PRODUCTION, self::ENV_STAGING], true)) {
            try {
                $diffService = $this->plugin->diffService;
                $current = $diffService->getEntriesBySection();

                if ($compareEnv === self::ENV_LOCAL) {
                    $compareLabel = 'Local (fake diff for testing)';
                    $compareResult = $diffService->getFakeCompareResult($current);
                } else {
                    $baseUrl = $this->getTargetUrlForEnvironment($compareEnv);
                    $token = $this->plugin->settingsService->getResolvedApiKey();
                    if ($token === '') {
                        $compareError = Craft::t('craft-content-diff', 'Set the API key in plugin Settings on both environments to compare with {env}.', ['env' => $compareEnv]);
                    } elseif ($baseUrl === '') {
                        $compareError = Craft::t('craft-content-diff', 'Set the {env} URL in Settings.', ['env' => $compareEnv]);
                    } else {
                        [$httpUser, $httpPassword] = $this->getHttpAuthForEnvironment($compareEnv);
                        $envLabel = $compareEnv === self::ENV_PRODUCTION ? 'Production' : 'Staging';
                        $remote = $diffService->fetchRemoteEntriesBySection($baseUrl, $token, $httpUser, $httpPassword, $compareEnv, $envLabel);
                        if (empty($remote)) {
                            $specificError = $diffService->getLastFetchError();
                            $compareError = $specificError !== null && $specificError !== ''
                                ? $specificError
                                : Craft::t('craft-content-diff', 'Could not fetch data from {env}. Check URL, API key, and HTTP auth if the site is behind auth.', ['env' => $compareEnv]);
                        } else {
                            $compareLabel = $compareEnv === self::ENV_PRODUCTION ? 'Production' : 'Staging';
                            $compareResult = $diffService->compare($current, $remote);
                        }
                    }
                }
            } catch (\Throwable $e) {
                Craft::error('Content Diff: compare failed. ' . $e->getMessage(), 'craft-content-diff');
                $compareError = Craft::t('craft-content-diff', 'An error occurred while comparing. Check the logs.');
            }
        }

        $fieldTypes = $this->getFieldTypesMap();
        if ($compareResult !== null) {
            try {
                $siteId = Craft::$app->getSites()->getCurrentSite()->id;
                $compareResult = $this->plugin->diffService->enrichCompareResultWithAssetLabels($compareResult, $siteId);
            } catch (\Throwable $e) {
                Craft::warning('Content Diff: enrich compare result failed. ' . $e->getMessage(), 'craft-content-diff');
            }
        }

        $apiKey = $this->plugin->settingsService->getResolvedApiKey();
        $diffEnv = $currentEnv === self::ENV_DEV ? self::ENV_LOCAL : $currentEnv;
        $diffJsonParams = ['environment' => $diffEnv];
        if ($apiKey !== '') {
            $diffJsonParams['token'] = $apiKey;
        }
        $diffJsonUrl = UrlHelper::actionUrl('craft-content-diff/diff', $diffJsonParams);

        return $this->renderTemplate('craft-content-diff/dashboard/index', [
            'currentEnvironment' => $currentEnv,
            'compareTargets' => $compareTargets,
            'compareResult' => $compareResult,
            'compareLabel' => $compareLabel,
            'compareError' => $compareError,
            'fieldTypes' => $fieldTypes,
            'diffJsonUrl' => $diffJsonUrl,
        ]);
    }

    /**
     * Field handle => field type display name (e.g. "Plain Text", "Matrix", "Assets").
     *
     * @return array<string, string>
     */
    private function getFieldTypesMap(): array
    {
        $map = [];
        foreach (Craft::$app->getFields()->getAllFields() as $field) {
            $map[$field->handle] = $field::displayName();
        }
        return $map;
    }

    /**
     * Current environment: ENVIRONMENT or CRAFT_ENVIRONMENT if set; otherwise inferred from
     * current site URL vs configured Production URL / Staging URL.
     */
    private function getCurrentEnvironment(): string
    {
        $env = App::env('ENVIRONMENT') ?? App::env('CRAFT_ENVIRONMENT');
        if ($env !== null && $env !== '') {
            return strtolower((string) $env);
        }
        $currentUrl = rtrim((string) Craft::$app->getSites()->getCurrentSite()->getBaseUrl(), '/');
        if ($currentUrl === '') {
            return self::ENV_DEV;
        }
        $productionUrl = $this->plugin->settingsService->getResolvedProductionUrl();
        $stagingUrl = $this->plugin->settingsService->getResolvedStagingUrl();
        if ($productionUrl !== '' && $this->urlMatches($currentUrl, $productionUrl)) {
            return self::ENV_PRODUCTION;
        }
        if ($stagingUrl !== '' && $this->urlMatches($currentUrl, $stagingUrl)) {
            return self::ENV_STAGING;
        }
        return self::ENV_DEV;
    }

    /**
     * Whether the current site URL is the same environment as the configured URL (same scheme + host).
     */
    private function urlMatches(string $currentUrl, string $configuredUrl): bool
    {
        $current = parse_url($currentUrl);
        $configured = parse_url($configuredUrl);
        if (!is_array($current) || !is_array($configured)) {
            return false;
        }
        $currentHost = ($current['scheme'] ?? '') . '://' . ($current['host'] ?? '');
        $configuredHost = ($configured['scheme'] ?? '') . '://' . ($configured['host'] ?? '');
        return $currentHost !== '' && $currentHost === $configuredHost;
    }

    /**
     * Compare targets available from the current environment.
     * Production → [staging]; Staging → [production]; Dev → [local, production, staging].
     *
     * @return array<int, array{key: string, label: string, url: string, compareUrl: string}>
     */
    private function getCompareTargetsForCurrentEnvironment(string $currentEnv): array
    {
        $compareTargets = [];

        if ($currentEnv === self::ENV_DEV) {
            $siteUrl = rtrim(Craft::$app->getSites()->getCurrentSite()->getBaseUrl(), '/');
            $compareTargets[] = [
                'key' => self::ENV_LOCAL,
                'label' => 'Local',
                'url' => $siteUrl,
                'compareUrl' => UrlHelper::cpUrl('craft-content-diff/dashboard', ['compare' => self::ENV_LOCAL]),
            ];
        }

        $targets = [];
        if ($currentEnv === self::ENV_PRODUCTION) {
            $targets[] = [self::ENV_STAGING, 'Staging'];
        } elseif ($currentEnv === self::ENV_STAGING) {
            $targets[] = [self::ENV_PRODUCTION, 'Production'];
        } else {
            $targets[] = [self::ENV_PRODUCTION, 'Production'];
            $targets[] = [self::ENV_STAGING, 'Staging'];
        }

        foreach ($targets as [$key, $label]) {
            if (!$this->hasTargetUrlForEnvironment($key)) {
                continue;
            }
            $url = $this->getTargetUrlForEnvironment($key);
            if ($url !== '' && (str_starts_with($url, 'http://') || str_starts_with($url, 'https://'))) {
                $compareTargets[] = [
                    'key' => $key,
                    'label' => $label,
                    'url' => $url,
                    'compareUrl' => UrlHelper::cpUrl('craft-content-diff/dashboard', ['compare' => $key]),
                ];
            }
        }
        return $compareTargets;
    }

    /**
     * Whether the target environment has a valid base URL configured (from Settings).
     */
    private function hasTargetUrlForEnvironment(string $environment): bool
    {
        $url = $this->getTargetUrlForEnvironment($environment);
        return $url !== '' && (str_starts_with($url, 'http://') || str_starts_with($url, 'https://'));
    }

    /**
     * HTTP Basic auth credentials for the given environment (from Settings; values can be env var aliases).
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function getHttpAuthForEnvironment(string $environment): array
    {
        if ($environment === self::ENV_PRODUCTION) {
            $user = $this->plugin->settingsService->getResolvedProductionHttpUser();
            $pass = $this->plugin->settingsService->getResolvedProductionHttpPassword();
        } else {
            $user = $this->plugin->settingsService->getResolvedStagingHttpUser();
            $pass = $this->plugin->settingsService->getResolvedStagingHttpPassword();
        }
        return ($user !== '' && $pass !== '')
            ? [$user, $pass]
            : [null, null];
    }

    /**
     * Base URL for the given environment (from Settings; value can be literal or env var alias).
     */
    private function getTargetUrlForEnvironment(string $environment): string
    {
        return $environment === self::ENV_PRODUCTION
            ? $this->plugin->settingsService->getResolvedProductionUrl()
            : $this->plugin->settingsService->getResolvedStagingUrl();
    }
}
