<?php
/**
 * SsMembership plugin for Craft CMS 3.x
 *
 * Craft CMS membership with stripe.
 *
 * @link      http://www.systemseeders.com/
 * @copyright Copyright (c) 2021 ssplugin
 */

namespace ssplugin\ssmembership\models;

use ssplugin\ssmembership\SsMembership;

use Craft;
use craft\base\Model;

/**
 * Membership Model
 *
 * Models are containers for data. Just about every time information is passed
 * between services, controllers, and templates in Craft, it’s passed via a model.
 *
 * https://craftcms.com/docs/plugins/models
 *
 * @author    ssplugin
 * @package   SsMembership
 * @since     1.0.0
 */
class SsPaymentGateway extends Model
{

    /** @var int */
    public $id;

    /** @var int */
    public $name;
    public $integrationId;

    /** @var string */
    public $handle;

    /** @var string */
    public $livePublicKey;

    /** @var string */
    public $liveSecretKey;

    /** @var string */
    public $testPublicKey;

    /** @var string */
    public $testSecretKey;
    public $webhookUrl;
    public $webhookSecret;
    public $webhookUrlTest;
    public $webhookSecretTest;
    public $uid;

    /**
     * @var \DateTime
     */
    public $dateCreated;

    /**
     * @var \DateTime
     */
    public $dateUpdated;

    /** @var bool */
    public $liveMode;

    /**
     * @return string the name of the table associated with this ActiveRecord class.
     */
    public static function tableName()
    {
        return '{{ssmembership_paymentgateway}}';
    }
    /**
     * Returns the validation rules for attributes.
     *
     * Validation rules are used by [[validate()]] to check if attribute values are valid.
     * Child classes may override this method to declare different validation rules.
     *
     * More info: http://www.yiiframework.com/doc-2.0/guide-input-validation.html
     *
     * @return array
     */
    public function rules()
    {
        return [
            ['livePublicKey', 'required'],
            ['liveSecretKey', 'required'],
            ['testSecretKey', 'required'],
            ['testPublicKey', 'required'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function dateTimeAttributes(): array
    {
        return [
            'dateCreated',
            'dateUpdated',
        ];
    }
}
