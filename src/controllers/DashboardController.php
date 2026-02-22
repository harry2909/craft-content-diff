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
                    $token = App::parseEnv($this->plugin->getSettings()->apiKey ?? '');
                    if (!is_string($token) || $token === '') {
                        $compareError = Craft::t('craft-content-diff', 'Set the API key in plugin Settings on both environments to compare with {env}.', ['env' => $compareEnv]);
                    } elseif ($baseUrl === '') {
                        $compareError = Craft::t('craft-content-diff', 'Set the {env} URL in Settings.', ['env' => $compareEnv]);
                    } else {
                        [$httpUser, $httpPassword] = $this->getHttpAuthForEnvironment($compareEnv);
                        $remote = $diffService->fetchRemoteEntriesBySection($baseUrl, $token, $httpUser, $httpPassword);
                        if (empty($remote)) {
                            $compareError = Craft::t('craft-content-diff', 'Could not fetch data from {env}. Check URL, API key, and HTTP auth if the site is behind auth.', ['env' => $compareEnv]);
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

        return $this->renderTemplate('craft-content-diff/dashboard/index', [
            'currentEnvironment' => $currentEnv,
            'compareTargets' => $compareTargets,
            'compareResult' => $compareResult,
            'compareLabel' => $compareLabel,
            'compareError' => $compareError,
            'fieldTypes' => $fieldTypes,
            'diffJsonUrl' => UrlHelper::actionUrl('craft-content-diff/diff', ['environment' => 'local']),
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
     * Current environment: ENVIRONMENT, or CRAFT_ENVIRONMENT as fallback, or dev.
     */
    private function getCurrentEnvironment(): string
    {
        $env = App::env('ENVIRONMENT') ?? App::env('CRAFT_ENVIRONMENT');
        return $env ? strtolower((string) $env) : self::ENV_DEV;
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
        $settings = $this->plugin->getSettings();
        if ($environment === self::ENV_PRODUCTION) {
            $user = App::parseEnv($settings->productionHttpUser ?? '');
            $pass = App::parseEnv($settings->productionHttpPassword ?? '');
        } else {
            $user = App::parseEnv($settings->stagingHttpUser ?? '');
            $pass = App::parseEnv($settings->stagingHttpPassword ?? '');
        }
        return (is_string($user) && $user !== '' && is_string($pass) && $pass !== '')
            ? [$user, $pass]
            : [null, null];
    }

    /**
     * Base URL for the given environment (from Settings; value can be literal or env var alias).
     */
    private function getTargetUrlForEnvironment(string $environment): string
    {
        $settings = $this->plugin->getSettings();
        $url = $environment === self::ENV_PRODUCTION ? $settings->productionUrl : $settings->stagingUrl;
        $url = is_string($url) ? App::parseEnv($url) : '';
        return $url !== '' ? rtrim($url, '/') : '';
    }
}
