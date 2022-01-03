<?php
/**
 * SsMembership plugin for Craft CMS 3.x
 *
 * Craft CMS membership with stripe.
 *
 * @link      http://www.systemseeders.com/
 * @copyright Copyright (c) 2021 ssplugin
 */

namespace ssplugin\ssmembership\records;

use ssplugin\ssmembership\SsMembership;

use Craft;
use craft\db\ActiveRecord;

class Payment extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%ssmembership_payments}}';
    }
}
