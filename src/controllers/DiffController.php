<?php

namespace roadsterworks\craftcontentdiff\controllers;

use Craft;
use craft\helpers\App;
use craft\web\Controller;
use yii\web\Response;
use roadsterworks\craftcontentdiff\CraftContentDiff;

/**
 * Serves the diff JSON endpoint for server-to-server fetches.
 * Uses site action URL (/actions/...) so token auth applies without CP login.
 */
class DiffController extends Controller
{
    private const ENV_PRODUCTION = 'production';
    private const ENV_STAGING = 'staging';
    private const ENV_LOCAL = 'local';

    public $defaultAction = 'index';

    /**
     * Allow unauthenticated access to the diff action so server-to-server calls with
     * X-Content-Diff-Token can reach the action. Token is validated inside actionIndex().
     */
    protected array|int|bool $allowAnonymous = ['index'];

    /**
     * Disable CSRF for the diff action so remote fetches (with token header) are not blocked.
     */
    public function beforeAction($action): bool
    {
        if ($action->id === 'index') {
            $this->enableCsrfValidation = false;
        }
        return parent::beforeAction($action);
    }

    /**
     * craft-content-diff/diff action.
     * Access: X-Content-Diff-Token header (or ?token=) matching the API key in plugin Settings.
     * Prefer the header for server-to-server so the token never appears in URLs or logs.
     */
    public function actionIndex(): Response|string
    {
        $settings = CraftContentDiff::getInstance()->getSettings();
        $apiKey = App::parseEnv($settings->apiKey ?? '');
        $apiKey = is_string($apiKey) ? $apiKey : '';
        $token = Craft::$app->getRequest()->getHeaders()->get('X-Content-Diff-Token')
            ?? Craft::$app->getRequest()->getQueryParam('token');
        $token = is_string($token) ? $token : '';

        if ($apiKey === '') {
            Craft::$app->getResponse()->setStatusCode(401);
            $hint = str_starts_with((string)($settings->apiKey ?? ''), '$')
                ? ' API key is set to an env alias (e.g. $CRAFT_CONTENT_DIFF_API_KEY) but that env var is not set or not loaded. Add it to .env on this environment.'
                : ' API key is not set in plugin Settings on this environment.';
            return $this->asJson([
                'error' => 'Unauthorized',
                'message' => 'Missing or invalid X-Content-Diff-Token.' . $hint . ' Use the same key on both environments and send it in the header or ?token=.',
            ]);
        }

        if ($token === '') {
            Craft::$app->getResponse()->setStatusCode(401);
            return $this->asJson([
                'error' => 'Unauthorized',
                'message' => 'Missing X-Content-Diff-Token. Send the API key in the header (X-Content-Diff-Token) or query (?token=). Locally you can open: ' . $this->request->getAbsoluteUrl() . '?token=YOUR_API_KEY',
            ]);
        }

        if ($token !== $apiKey) {
            Craft::$app->getResponse()->setStatusCode(401);
            $hint = str_starts_with((string)($settings->apiKey ?? ''), '$')
                ? ' The token you sent does not match the value of the env var. Check .env (e.g. CRAFT_CONTENT_DIFF_API_KEY) and use that exact value in the header or ?token=.'
                : ' The token you sent does not match the API key in plugin Settings on this environment.';
            return $this->asJson([
                'error' => 'Unauthorized',
                'message' => 'Missing or invalid X-Content-Diff-Token.' . $hint . ' Use the same key on both environments.',
            ]);
        }

        $environment = Craft::$app->getRequest()->getQueryParam('environment', self::ENV_STAGING);
        $allowed = [self::ENV_PRODUCTION, self::ENV_STAGING, self::ENV_LOCAL];
        if (!in_array($environment, $allowed, true)) {
            $environment = self::ENV_STAGING;
        }

        $currentSiteUrl = rtrim(Craft::$app->getSites()->getCurrentSite()->getBaseUrl(), '/');
        $siteUrl = $environment === self::ENV_LOCAL
            ? $currentSiteUrl
            : $this->getUrlForEnvironment($environment);

        $diffService = CraftContentDiff::getInstance()->diffService;
        $entriesBySection = $diffService->getEntriesBySection();

        return $this->asJson([
            'siteUrl' => $siteUrl,
            'environment' => $environment,
            'entriesBySection' => $entriesBySection,
        ]);
    }

    /**
     * Base URL for the given environment (from Settings; value can be env var alias).
     */
    private function getUrlForEnvironment(string $environment): string
    {
        if ($environment === self::ENV_LOCAL) {
            return rtrim(Craft::$app->getSites()->getCurrentSite()->getBaseUrl(), '/');
        }
        $settings = CraftContentDiff::getInstance()->getSettings();
        $url = $environment === self::ENV_PRODUCTION ? $settings->productionUrl : $settings->stagingUrl;
        $url = is_string($url) ? App::parseEnv($url) : '';
        return $url !== '' ? rtrim($url, '/') : '';
    }
}
