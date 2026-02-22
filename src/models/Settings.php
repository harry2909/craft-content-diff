<?php

namespace roadsterworks\craftcontentdiff\models;

use craft\base\Model;
use craft\behaviors\EnvAttributeParserBehavior;

/**
 * Plugin settings: API key, URLs, and optional HTTP Basic auth for staging/production.
 * Values can be literals or env var aliases (e.g. $CONTENT_DIFF_PRODUCTION_URL); resolved at runtime.
 */
class Settings extends Model
{
    /** @var string|null */
    public ?string $apiKey = '';

    /** @var string|null */
    public ?string $productionUrl = '';

    /** @var string|null */
    public ?string $stagingUrl = '';

    /** @var string|null */
    public ?string $stagingHttpUser = '';

    /** @var string|null */
    public ?string $stagingHttpPassword = '';

    /** @var string|null */
    public ?string $productionHttpUser = '';

    /** @var string|null */
    public ?string $productionHttpPassword = '';

    /**
     * @inheritdoc
     */
    public function behaviors(): array
    {
        return [
            'parser' => [
                'class' => EnvAttributeParserBehavior::class,
                'attributes' => [
                    'apiKey',
                    'productionUrl',
                    'stagingUrl',
                    'stagingHttpUser',
                    'stagingHttpPassword',
                    'productionHttpUser',
                    'productionHttpPassword',
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        return [
            [
                [
                    'apiKey',
                    'productionUrl', 'stagingUrl',
                    'stagingHttpUser', 'stagingHttpPassword',
                    'productionHttpUser', 'productionHttpPassword',
                ],
                'string',
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected function defineAttributeNames(): array
    {
        return [
            'apiKey' => 'API key',
            'productionUrl' => 'Production URL',
            'stagingUrl' => 'Staging URL',
            'stagingHttpUser' => 'Staging HTTP user',
            'stagingHttpPassword' => 'Staging HTTP password',
            'productionHttpUser' => 'Production HTTP user',
            'productionHttpPassword' => 'Production HTTP password',
        ];
    }
}
