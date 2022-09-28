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
use craft\helpers\Template as TemplateHelper;

/**
 * SsMembership Variable
 *
 * Craft allows plugins to provide their own template variables, accessible from
 * the {{ craft }} global variable (e.g. {{ craft.ssMembership }}).
 *
 * https://craftcms.com/docs/plugins/variables
 *
 * @author    ssplugin
 * @package   SsMembership
 * @since     1.0.0
 */
class SsMembershipVariable
{
    // Public Methods
    // =========================================================================

    /**
     * Whatever you want to output to a Twig template can go into a Variable method.
     * You can have as many variable functions as you want.  From any Twig template,
     * call it like this:
     *
     *     {{ craft.ssMembership.exampleVariable }}
     *
     * Or, if your variable requires parameters from Twig:
     *
     *     {{ craft.ssMembership.exampleVariable(twigValue) }}
     *
     * @param null $optional
     * @return string
     */
    public function mebershipPlan( $handle = null )
    {
        if( !empty( $handle ) ) {
            $membership =  SsMembership::getInstance()->membershipPlan->getMembershipPlanByHandle( $handle );
        } else {
            $membership =  SsMembership::getInstance()->membershipPlan->getAllMembershipPlan( );
        }
        if( !empty( $membership ) ) {
            return $membership;
        }
        return false;
    }

    public function getActiveMembershipPlan( $handle = null )
    {       
        $membership =  SsMembership::getInstance()->membershipPlan->getActiveMembershipPlan( );     
        if( !empty( $membership ) ) {
            return $membership;
        }
        return false;
    }

    public function planField( $params = null ) 
    {
        $request = Craft::$app->getRequest();
        if (!$request->isCpRequest
            && !$request->isConsoleRequest
        ) {
            $view = Craft::$app->getView();
            $view->registerAssetBundle( SsMembershipAsset::class, View::POS_END );
        }

        $oldMode = \Craft::$app->view->getTemplateMode();
        Craft::$app->view->setTemplateMode( View::TEMPLATE_MODE_CP );
        $html = \Craft::$app->view->renderTemplate( 'ss-membership/front/plans', [ 'params' => $params ] );
        Craft::$app->view->setTemplateMode( $oldMode );
        return TemplateHelper::raw($html);
    }

    public function paymentField( $params = null ) 
    {
        $params[ 'stripe_public_key' ] = '';
        $gateway = SsMembership::getInstance()->paymentGateway->getPaymentGateway();
        if( $gateway ) {
            if( !empty( $gateway->id ) ) {
                if( $gateway->liveMode ){
                    $params[ 'stripe_public_key' ] = $gateway->livePublicKey;
                } else {
                    $params[ 'stripe_public_key' ] = $gateway->testPublicKey;
                }
            }
        }
        $oldMode = \Craft::$app->view->getTemplateMode();
        Craft::$app->view->setTemplateMode( View::TEMPLATE_MODE_CP );
        $html = \Craft::$app->view->renderTemplate( 'ss-membership/front', [ 'params' => $params ] );
        Craft::$app->view->setTemplateMode( $oldMode );
        return TemplateHelper::raw($html);
    }

    public function getPlan( $handle = null )
    {
        $currentUser = Craft::$app->getUser()->getIdentity();
        if( $currentUser ) {
            $subscriptions = SsMembership::getInstance()->subscription->getUserActiveSub( $currentUser->id );

            if( !$subscriptions ) {

                if( !empty( $handle ) ) {                   
                    $membership = SsMembership::getInstance()->membershipPlan->getMembershipPlanByHandle( $handle );
                } else {
                    // fetch only enabled membership plan.
                    $membership = SsMembership::getInstance()->membershipPlan->getAllMembershipPlan( ['enabled' => 1] );
                }
            }
            
            if( !empty( $membership ) ) {
                return $membership;
            }            
        }
        return null;
    }

    // public function canSubscribe(){

    //     $currentUser = Craft::$app->getUser()->getIdentity();
    //     if( $currentUser ) {
    //         $subscriptions = SsMembership::getInstance()->subscription->getUserActiveSub( $currentUser->id );
    //         if( !$subscriptions ) {
    //             $membership = SsMembership::getInstance()->membershipPlan->getAllMembershipPlan( ['enabled' => 1] );
    //             if( !empty( $membership ) ) {
    //                 return true;
    //             }
    //         }
    //     }      
    //     return false;
    // }

    public function isLive( $handle = null ){
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
}
