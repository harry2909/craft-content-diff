<?php

namespace roadsterworks\craftcontentdiff;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use roadsterworks\craftcontentdiff\models\Settings;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\UrlHelper;
use craft\web\UrlManager;
use craft\web\View;
use yii\base\Event;
use roadsterworks\craftcontentdiff\controllers\DiffController;
use roadsterworks\craftcontentdiff\controllers\SettingsController;
use roadsterworks\craftcontentdiff\services\SettingsService;
use roadsterworks\craftcontentdiff\services\DiffService;

/**
 * Craft Content Diff plugin
 *
 * @property-read SettingsService $settingsService
 * @property-read DiffService $diffService
 *
 * @method static CraftContentDiff getInstance()
 * @method Settings getSettings()
 * @author roadsterworks <roadsterworks@gmail.com>
 * @copyright roadsterworks
 * @license https://craftcms.github.io/license/ Craft License
 */
class CraftContentDiff extends Plugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSection = true;
    public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => [
               'settingsService' => SettingsService::class,
               'diffService' => DiffService::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        $this->controllerMap = [
            'diff' => DiffController::class,
            'settings' => SettingsController::class,
        ];

        $this->attachEventHandlers();
    }

    private function attachEventHandlers(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['craft-content-diff/dashboard'] = 'craft-content-diff/dashboard/index';
                $event->rules['craft-content-diff/settings'] = 'craft-content-diff/settings/index';
            }
        );

        Event::on(
            View::class,
            View::EVENT_REGISTER_SITE_TEMPLATE_ROOTS,
            function (RegisterTemplateRootsEvent $event) {
                $event->roots['craft-content-diff'] = $this->getBasePath() . '/templates';
            }
        );
    }

    /**
     * @inheritdoc
     */
    public function getSettingsResponse(): \craft\web\Response
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('craft-content-diff/settings'));
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem(): ?array
    {
        $nav = parent::getCpNavItem();
        $nav['label'] = $this->getPluginName();

        $nav['url'] = 'craft-content-diff/dashboard';

        $nav['subnav']['dashboard'] = [
            'label' => Craft::t('craft-content-diff', 'Dashboard'),
            'url' => 'craft-content-diff/dashboard',
        ];

        $nav['subnav']['settings'] = [
            'label' => Craft::t('craft-content-diff', 'Settings'),
            'url' => 'craft-content-diff/settings',
        ];

        return $nav;
    }

    /**
     * @inheritdoc
     */
    public function getPluginName(): string
    {
        return Craft::t('craft-content-diff', 'Craft Content Diff');
    }

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }
}
