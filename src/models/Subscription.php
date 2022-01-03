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
use ssplugin\ssmembership\records\Subscription as SubscriptionRecord;
use ssplugin\ssmembership\models\Membership as MembershipModel;
use ssplugin\ssmembership\models\Payment;
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
class Subscription extends Model
{

    /** @var int */
    public $id;
    public $uid;

    /** @var int */
    public $userId;
    public $membershipPlanId;
    public $interval_count;

    /** @var string */
    public $stripeSubId;
    public $stripeCustomerId;
    public $stripePriceId;

    /** @var string */
    public $name;
    public $payer_email;
    public $currency;
    public $interval;
    public $status;
    public $isLive;
    public $cancelType;

    /** @var float */
    public $amount;

    /**
     * @var \DateTime
     */
    public $subStartDate;
    public $subEndDate;
    public $canceledAt;
    public $cancelAt;
    public $dateCreated;
    public $dateUpdated;
    protected $membershipPlan;
    protected $payments;
    protected $log;
    /**
     * @return string the name of the table associated with this ActiveRecord class.
     */
    public static function tableName()
    {
        return '{{ssmembership_subscription}}';
    }
    /**
     * @inheritdoc
     */
    public function dateTimeAttributes(): array
    {
        return [
            'subStartDate',
            'subEndDate',
            'canceledAt',
            'cancelAt',
            'dateCreated',
            'dateUpdated',
        ];
    }

    public function getUser(){
        $user = Craft::$app->users->getUserById($this->userId);
        return $user;
    }

    public function getMembershipPlan(): MembershipModel
    {
        if ( !$this->membershipPlan && $this->membershipPlanId ) {
            $this->membershipPlan = SsMembership::getInstance()->membershipPlan->getMembershipPlanById( $this->membershipPlanId );
        }
        return $this->membershipPlan;
    }

    public function getPayments()
    {
        if ( $this->id ) {
            $this->payments = SsMembership::getInstance()->payment->getPaymentBySubId( $this->id );
        }
        return $this->payments;
    }

    public function getPlanName(): string
    {
        return $this->name;
    }

    public function getLogs(){
        $this->log = SsMembership::getInstance()->log->getLogBySubId( $this->id );
        return $this->log;
    }
}
