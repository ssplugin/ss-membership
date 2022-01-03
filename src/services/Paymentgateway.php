<?php
/**
 * SsMembership plugin for Craft CMS 3.x
 *
 * Craft CMS membership with stripe.
 *
 * @link      http://www.systemseeders.com/
 * @copyright Copyright (c) 2021 ssplugin
 */

namespace ssplugin\ssmembership\services;

use ssplugin\ssmembership\SsMembership;

use Craft;
use craft\base\Component;
use craft\db\Query;
use ssplugin\ssmembership\models\SsPaymentGateway;
use ssplugin\ssmembership\records\SsPaymentGateway as SsPaymentGatewayrecord;
use craft\helpers\UrlHelper;
/**
 * Membership Service
 *
 * All of your plugin’s business logic should go in services, including saving data,
 * retrieving data, etc. They provide APIs that your controllers, template variables,
 * and other plugins can interact with.
 *
 * https://craftcms.com/docs/plugins/services
 *
 * @author    ssplugin
 * @package   SsMembership
 * @since     1.0.0
 */
class Paymentgateway extends Component
{
    public function getPaymentGateway()
    {
        $rows = $this->_createQuery()
            ->one();
        return new SsPaymentGateway($rows);
    }

    
    public function getPaymentGatewayById(int $id)
    {
        $row = $this->_createQuery()
            ->where(['id' => $id])
            ->one();

        if (!$row) {
            return null;
        }
        
        return new SsPaymentGateway($row);
    }

    private function _createQuery(): Query
    {
        return (new Query)
            ->select([
                'id',
                'integrationId',
                'name',
                'handle',
                'livePublicKey',
                'liveSecretKey',
                'testPublicKey',
                'testSecretKey',
                'liveMode',
                'webhookUrl',
                'webhookSecret',
                'webhookUrlTest',
                'webhookSecretTest',
                'dateCreated',
                'dateUpdated',
                'uid'
            ])
            ->from('{{%ssmembership_paymentgateway}}')
            ->orderBy('dateCreated ASC');
    }

    private function _getPaymentGatewayModels(array $rows): array
    {
        return array_map(function ($row) {
            return new SsPaymentGateway($row);
        }, $rows);
    }

    public function savePaymentGateway(SsPaymentGateway $paymentGateway): bool
    {
        $isExist = !$paymentGateway->id;

        if (!$isExist) {
            $record = SsPaymentGatewayrecord::findOne($paymentGateway->id);
        } else {
            $record = new SsPaymentGatewayrecord;
        }
      
        $record->livePublicKey = $paymentGateway->livePublicKey;
        $record->liveSecretKey = $paymentGateway->liveSecretKey;
        $record->testPublicKey = $paymentGateway->testPublicKey;
        $record->testSecretKey = $paymentGateway->testSecretKey;
        $record->liveMode = $paymentGateway->liveMode;
        $record->integrationId = '1';
        $record->name = 'Stripe';
        $record->handle = 'stripe';
        
        if (!$record->save()) {
            return false;
        }
        $this->handleWebhook( $record );
        $paymentGateway->id = $record->id;
        $paymentGateway->webhookUrl = $record->webhookUrl;
        $paymentGateway->webhookUrlTest = $record->webhookUrlTest;
        return true;
    }

    protected function handleWebhook( $record )
    {
        if( $record ) {
            $url = UrlHelper::baseUrl().'ss-membership/event';

            if( $record->liveMode ) {
                $url = parse_url($url);

                // Live mode webhook url must be "https"
                if( $url['scheme'] == 'https' ) {
                    $liveSecret = $record->liveSecretKey;
                    $stripe = new \Stripe\StripeClient(
                        $liveSecret
                    );
                    if( empty( $record->webhookSecret )) {                    
                        $this->createWebhook( $stripe, $record );
                    }elseif ($url != $record->webhookUrl ) { 
                        $this->updateWebhook( $stripe, $record ); 
                    }
                }
            } else {
                $testSecret = $record->testSecretKey;
                $stripe = new \Stripe\StripeClient(
                    $testSecret
                );                
                if( empty( $record->webhookSecretTest )) {
                    $this->createWebhook( $stripe, $record );
                }elseif ($url != $record->webhookUrlTest ) { 

                    $this->updateWebhook( $stripe, $record ); 
                }
            }            
        }
    }

    protected function createWebhook( $stripe, $record )
    {
        $url = UrlHelper::baseUrl().'ss-membership/event';           
        if( !empty( $url ) ) {                
            $webhook = $stripe->webhookEndpoints->create([
                'url' => $url,
                'enabled_events' => [
                    'customer.subscription.deleted',
                    'customer.subscription.updated'
                ],
            ]);

            if( $webhook->livemode ){
                $record->webhookSecret = $webhook->id;
                $record->webhookUrl = $webhook->url;
            }else{
                $record->webhookSecretTest = $webhook->id;
                $record->webhookUrlTest = $webhook->url;
            }
            $record->save();
        }
    }

    protected function updateWebhook( $stripe, $record )
    {        
        $url = UrlHelper::baseUrl().'ss-membership/event';
        if( !empty( $url ) ) {                             
            if( $record->liveMode ){
                $webhook = $stripe->webhookEndpoints->update(
                    $record->webhookSecret,
                    ['url' => $url]
                );                    
                $record->webhookUrl = $webhook->url;
                $record->save();
            } else {
                $webhook = $stripe->webhookEndpoints->update(
                    $record->webhookSecretTest,
                    ['url' => $url]
                ); 
                $record->webhookUrlTest = $webhook->url;
                $record->save();
            }
        }
    }
}
