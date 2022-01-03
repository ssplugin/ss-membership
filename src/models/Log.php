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
use ssplugin\ssmembership\records\Log as LogRecord;
use ssplugin\ssmembership\models\Log as LogModel;

class Log extends Model
{

    /** @var int */
    public $id;
    public $uid;
    public $referenceId;

    public $logMessage;
    public $referenceBy;
    /**
     * @var \DateTime
     */
    public $dateCreated;
    public $dateUpdated;
    
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
