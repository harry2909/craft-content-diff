<?php

namespace roadsterworks\craftcontentdiff\services;

use craft\helpers\App;
use roadsterworks\craftcontentdiff\CraftContentDiff;
use yii\base\Component;

/**
 * Settings-related logic: resolved values from App::env() with fallback to stored settings.
 */
class SettingsService extends Component
{
    /**
     * API key: App::env('CRAFT_CONTENT_DIFF_API_KEY') or literal from settings. Empty if neither set.
     */
    public function getResolvedApiKey(): string
    {
        $v = App::env('CRAFT_CONTENT_DIFF_API_KEY');
        if ($v !== null && $v !== false && $v !== '') {
            return (string) $v;
        }
        $raw = CraftContentDiff::getInstance()->getSettings()->apiKey ?? '';
        return is_string($raw) ? $raw : '';
    }

    /**
     * Production URL: App::env('CRAFT_CONTENT_DIFF_PRODUCTION_URL') or literal from settings.
     */
    public function getResolvedProductionUrl(): string
    {
        $v = App::env('CRAFT_CONTENT_DIFF_PRODUCTION_URL');
        if ($v !== null && $v !== false && $v !== '') {
            return rtrim((string) $v, '/');
        }
        $raw = CraftContentDiff::getInstance()->getSettings()->productionUrl ?? '';
        return is_string($raw) && $raw !== '' ? rtrim($raw, '/') : '';
    }

    /**
     * Staging URL: App::env('CRAFT_CONTENT_DIFF_STAGING_URL') or literal from settings.
     */
    public function getResolvedStagingUrl(): string
    {
        $v = App::env('CRAFT_CONTENT_DIFF_STAGING_URL');
        if ($v !== null && $v !== false && $v !== '') {
            return rtrim((string) $v, '/');
        }
        $raw = CraftContentDiff::getInstance()->getSettings()->stagingUrl ?? '';
        return is_string($raw) && $raw !== '' ? rtrim($raw, '/') : '';
    }

    /**
     * Staging HTTP Basic user: App::env('CRAFT_CONTENT_DIFF_STAGING_HTTP_USER') or literal.
     */
    public function getResolvedStagingHttpUser(): string
    {
        $v = App::env('CRAFT_CONTENT_DIFF_STAGING_HTTP_USER');
        if ($v !== null && $v !== false) {
            return (string) $v;
        }
        $raw = CraftContentDiff::getInstance()->getSettings()->stagingHttpUser ?? '';
        return is_string($raw) ? $raw : '';
    }

    /**
     * Staging HTTP Basic password: App::env('CRAFT_CONTENT_DIFF_STAGING_HTTP_PASSWORD') or literal.
     */
    public function getResolvedStagingHttpPassword(): string
    {
        $v = App::env('CRAFT_CONTENT_DIFF_STAGING_HTTP_PASSWORD');
        if ($v !== null && $v !== false) {
            return (string) $v;
        }
        $raw = CraftContentDiff::getInstance()->getSettings()->stagingHttpPassword ?? '';
        return is_string($raw) ? $raw : '';
    }

    /**
     * Production HTTP Basic user: App::env('CRAFT_CONTENT_DIFF_PRODUCTION_HTTP_USER') or literal.
     */
    public function getResolvedProductionHttpUser(): string
    {
        $v = App::env('CRAFT_CONTENT_DIFF_PRODUCTION_HTTP_USER');
        if ($v !== null && $v !== false) {
            return (string) $v;
        }
        $raw = CraftContentDiff::getInstance()->getSettings()->productionHttpUser ?? '';
        return is_string($raw) ? $raw : '';
    }

    /**
     * Production HTTP Basic password: App::env('CRAFT_CONTENT_DIFF_PRODUCTION_HTTP_PASSWORD') or literal.
     */
    public function getResolvedProductionHttpPassword(): string
    {
        $v = App::env('CRAFT_CONTENT_DIFF_PRODUCTION_HTTP_PASSWORD');
        if ($v !== null && $v !== false) {
            return (string) $v;
        }
        $raw = CraftContentDiff::getInstance()->getSettings()->productionHttpPassword ?? '';
        return is_string($raw) ? $raw : '';
    }
}
