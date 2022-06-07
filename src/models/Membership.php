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
use ssplugin\ssmembership\records\Membership as MembershipRecord;
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
class Membership extends Model
{

    /** @var int */
    public $id;
    public $uid;

    /** @var int */
    public $gatewayId;
    public $userGroupId ;

    /** @var string */
    public $handle;

    /** @var string */
    public $name;
    public $prodId;
    public $priceId;
    public $isLive;

    /** @var float */
    public $amount;

    /** @var string */
    public $currency;

    /** @var string */
    public $interval;

    /**
     * @var \DateTime
     */
    public $dateCreated;

    /**
     * @var \DateTime
     */
    public $dateUpdated;

    /** @var bool */
    public $enabled;

    /**
     * @return string the name of the table associated with this ActiveRecord class.
     */
    public static function tableName()
    {
        return '{{ssmembership_plan}}';
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

    public function rules(): array
    {
        return [
            ['name', 'required'],
            ['amount', 'required'],
            ['amount', 'number', 'min' => 1],
            ['interval', 'required'],
            ['userGroupId', 'required'],
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

    public function getUserGroup(){
        return Craft::$app->getUserGroups()->getGroupById( $this->userGroupId );
    }
}
