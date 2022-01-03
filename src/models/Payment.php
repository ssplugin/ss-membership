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
use ssplugin\ssmembership\records\Payment as PaymentsRecord;
use ssplugin\ssmembership\models\Payment as PaymentsModel;
use ssplugin\ssmembership\models\Subscription;

class Payment extends Model
{

    /** @var int */
    public $id;
    public $uid;
    public $paymentId;
    public $subscriptionId ;

    public $paymentData;
    public $status;

    /** @var float */
    public $amount;

    /**
     * @var \DateTime
     */
    public $payDate;
    public $dateCreated;
    public $dateUpdated;
    /**
     * @return string the name of the table associated with this ActiveRecord class.
     */
    public static function tableName()
    {
        return '{{ssmembership_payments}}';
    }
    /**
     * @inheritdoc
     */
    public function dateTimeAttributes(): array
    {
        return [
            'dateCreated',
            'dateUpdated',
            'payDate'
        ];
    }
}
