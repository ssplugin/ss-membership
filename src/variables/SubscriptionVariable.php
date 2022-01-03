<?php
/**
 * SsMembership plugin for Craft CMS 3.x
 *
 * Craft CMS membership with stripe.
 *
 * @link      http://www.systemseeders.com/
 * @copyright Copyright (c) 2021 ssplugin
 */

namespace ssplugin\ssmembership\variables;

use ssplugin\ssmembership\SsMembership;
use Craft;
use craft\web\View;
use ssplugin\ssmembership\assetbundles\ssmembership\SsMembershipAsset;

class SubscriptionVariable
{
    public function getSubscription()
    {
        $currentUser = Craft::$app->getUser()->getIdentity();
        if( $currentUser ) {
            $subscriptions = SsMembership::getInstance()->subscription->getSubscriptionByUserId( [ 'userId' => $currentUser->id ] );
            return $subscriptions;
        }
        return null;
    }

    public function getSubscriptionById()
    {        
        $subscriptions = SsMembership::getInstance()->subscription->getAllSubscription();
        return $subscriptions;
    }
}
