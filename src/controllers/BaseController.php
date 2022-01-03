<?php
/**
 * SsMembership plugin for Craft CMS 3.x
 *
 * Craft CMS membership with stripe.
 *
 * @link      http://www.systemseeders.com/
 * @copyright Copyright (c) 2021 ssplugin
 */

namespace ssplugin\ssmembership\controllers;

use ssplugin\ssmembership\SsMembership;

use Craft;
use craft\web\Controller;
use craft\db\Query;

use ssplugin\ssmembership\records\Membership;
use Stripe\Exception\InvalidRequestException;

class BaseController extends Controller
{
    public function getInstance()
    {
        return SsMembership::getInstance();
    }

    public function stripeClient()
    {
        $stripe = new \Stripe\StripeClient(
            SsMembership::$STRIPE_SECRET
        );
        
        return $stripe;
    }

    protected function createLog( $logMsg )
    {
        if( !empty( $logMsg ) ) {
           Craft::warning( $logMsg, 'ss-membership' ); 
        }
    }

    public function camelCase( $string )
    {
        if($string){
            $clear = trim(preg_replace('/ +/', ' ', preg_replace('/[^A-Za-z0-9 ]/', ' ', urldecode(html_entity_decode(strip_tags($string))))));
            return lcfirst(str_replace(' ', '', ucwords(preg_replace('/^a-z0-9]+/', ' ',$clear))));
        }
    }

    public function isLive()
    {
        $gateway = SsMembership::getInstance()->paymentGateway->getPaymentGateway();
        if( $gateway ){
            if( !empty( $gateway->id ) ) {
                if( $gateway->liveMode ){
                    return true;
                }
            }            
        }
        return false;
    }

    public function retriveSub( $subId )
    {
        if( $subId ){
            try {
                $stripe = $this->stripeClient();
                $subscription = $stripe->subscriptions->retrieve(
                    $subId
                );
                return $subscription;
            } catch ( InvalidRequestException $e ) {
                return null;
            }
        }

        return null;
    }

    public function Log( $message, $id, $refBy = null )
    {
        // $stripe = $this->stripeClient();
        // $events = $stripe->events->all(
        //     ['type' => 'customer.subscription.updated']
        // );
        
        // echo "<pre>";
        // print_r($events);
        // exit();

        if( $message && $id ) {
            $this->getInstance()->log->saveLog( $message, $id, $refBy );
        }
    }
}
