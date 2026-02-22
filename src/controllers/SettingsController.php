<?php

namespace roadsterworks\craftcontentdiff\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;
use roadsterworks\craftcontentdiff\CraftContentDiff;
use roadsterworks\craftcontentdiff\models\Settings;
use yii\web\BadRequestHttpException;
use yii\web\MethodNotAllowedHttpException;

/**
 * Plugin settings: API key, URLs, HTTP auth (values can be literals or env var aliases).
 */
class SettingsController extends Controller
{
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
     * Renders the settings page.
     */
    public function actionIndex(): Response
    {
        $this->requireCpRequest();

        $settings = $this->plugin->getSettings();

        return $this->renderTemplate('craft-content-diff/settings/index', [
            'settings' => $settings,
        ]);
    }

    /**
     * Save plugin settings. Stored values can be literals or env var aliases (e.g. $CONTENT_DIFF_STAGING_URL).
     *
     * @throws BadRequestHttpException
     * @throws MethodNotAllowedHttpException
     */
    public function actionSaveSettings(): Response
    {
        $this->requirePostRequest();
        $this->requireCpRequest();

        $settingsData = Craft::$app->getRequest()->getBodyParam('settings', []);
        $existing = $this->plugin->getSettings();
        if (($settingsData['stagingHttpPassword'] ?? '') === '') {
            $settingsData['stagingHttpPassword'] = $existing->stagingHttpPassword ?? '';
        }
        if (($settingsData['productionHttpPassword'] ?? '') === '') {
            $settingsData['productionHttpPassword'] = $existing->productionHttpPassword ?? '';
        }
        $settings = new Settings($settingsData);

        if (Craft::$app->plugins->savePluginSettings($this->plugin, $settings->toArray())) {
            Craft::$app->session->setNotice(Craft::t('craft-content-diff', 'Settings saved.'));
            return $this->redirectToPostedUrl();
        }

        Craft::$app->session->setError(Craft::t('craft-content-diff', "Couldn't save settings."));
        return $this->renderTemplate('craft-content-diff/settings/index', [
            'settings' => $settings,
        ]);
    }
}
