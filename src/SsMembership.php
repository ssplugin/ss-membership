<?php
/**
 * SsMembership plugin for Craft CMS 3.x
 *
 * Craft CMS membership with stripe.
 *
 * @link      http://www.systemseeders.com/
 * @copyright Copyright (c) 2021 ssplugin
 */

namespace ssplugin\ssmembership;

use ssplugin\ssmembership\services\Membershipplan as MembershipService;
use ssplugin\ssmembership\variables\SsMembershipVariable;
use ssplugin\ssmembership\variables\SubscriptionVariable;
use ssplugin\ssmembership\models\Settings;

use Craft;
use Yii;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\web\UrlManager;
use craft\web\twig\variables\CraftVariable;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterCpNavItemsEvent;
use craft\web\twig\variables\Cp;
use yii\base\Event;
use craft\elements\User;
use craft\events\ModelEvent;
use craft\events\UserEvent;
use craft\events\RegisterUserActionsEvent;
use craft\events\UserAssignGroupEvent;
use craft\records\UserGroup;
use craft\services\Users;
use craft\controllers\UsersController;
use craft\web\View;
use ssplugin\ssmembership\assetbundles\ssmembership\SsMembershipAsset;
use ssplugin\ssmembership\services\Paymentgateway;
/**
 * Craft plugins are very much like little applications in and of themselves. We’ve made
 * it as simple as we can, but the training wheels are off. A little prior knowledge is
 * going to be required to write a plugin.
 *
 * For the purposes of the plugin docs, we’re going to assume that you know PHP and SQL,
 * as well as some semi-advanced concepts like object-oriented programming and PHP namespaces.
 *
 * https://docs.craftcms.com/v3/extend/
 *
 * @author    ssplugin
 * @package   SsMembership
 * @since     1.0.0
 *
 * @property  MembershipService $membership
 * @property  Settings $settings
 * @method    Settings getSettings()
 */
class SsMembership extends Plugin
{
    // Static Properties
    public static $CURRENCY = 'USD';
    public static $STRIPE_KEY;
    public static $STRIPE_SECRET;

    /**
     * Static property that is an instance of this plugin class so that it can be accessed via
     * SsMembership::$plugin
     *
     * @var SsMembership
     */
    public static $plugin;

    /**
     * To execute your plugin’s migrations, you’ll need to increase its schema version.
     *
     * @var string
     */
    public $schemaVersion = '1.0.3';

    /**
     * Set to `true` if the plugin should have a settings view in the control panel.
     *
     * @var bool
     */
    public $hasCpSettings = true;

    /**
     * Set to `true` if the plugin should have its own section (main nav item) in the control panel.
     *
     * @var bool
     */
    public $hasCpSection = true;    
    
    /**
     * Set our $plugin static property to this class so that it can be accessed via
     * SsMembership::$plugin
     *
     * Called after the plugin class is instantiated; do any one-time initialization
     * here such as hooks and events.
     *
     * If you have a '/vendor/autoload.php' file, it will be loaded for you automatically;
     * you do not need to load it in your init() method.
     *
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        $fileTarget = new \craft\log\FileTarget([
            'logFile' => '@storage/logs/ssmembership.log',
            'categories' => ['ss-membership']
        ]);
        Craft::getLogger()->dispatcher->targets[] = $fileTarget;       

        //Register services routes
        $this->setComponents([
            'paymentGateway' => \ssplugin\ssmembership\services\Paymentgateway::class,
            'membershipPlan' => \ssplugin\ssmembership\services\MembershipPlan::class,
            'subscription' => \ssplugin\ssmembership\services\Subscription::class,
            'payment' => \ssplugin\ssmembership\services\Payment::class,
            'log' => \ssplugin\ssmembership\services\Log::class,
        ]);

        // Register our site routes
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules = array_merge($event->rules, $this->getSiteUrlRules());
            }
        );

        // Register our CP routes
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules[ 'ss-membership/membership' ] = 'ss-membership/membership/index';
                $event->rules[ 'ss-membership/membership/add' ] = 'ss-membership/membership/add';
                $event->rules[ 'ss-membership/membership/<id:\d+>' ] = 'ss-membership/membership/edit';
                $event->rules[ 'ss-membership/config' ] = 'ss-membership/membership/payment';
                $event->rules[ 'ss-membership/subscriptions/<id:\d+>' ] = 'ss-membership/subscription';
                $event->rules[ 'ss-membership/subscription/<id:\d+>' ] = 'ss-membership/subscription/edit';
                $event->rules[ 'ss-membership/subscription/cancel' ] = 'ss-membership/subscription/cancel';               
                $event->rules[ 'ss-membership/event' ] = 'ss-membership/webhook/index';
            }
        );

        // Register our variables
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set( 'ssMembership', SsMembershipVariable::class );
                $variable->set( 'ssMembershipSubscription', SubscriptionVariable::class );
            }
        );
        
        Event::on( \craft\services\Elements::class, \craft\services\Elements::EVENT_AFTER_SAVE_ELEMENT, function( Event $event ) {
            if ( $event->element instanceof \craft\elements\User ) {
                $request = Craft::$app->getRequest()->getBodyParams();
                //check user is register with ssmembership
                if( isset( $request[ 'stripeToken' ] ) && !empty( $request[ 'stripeToken' ] ) && isset( $request[ 'ssplan_id' ] ) && !empty( $request[ 'ssplan_id' ] ) ) {
                    $user = $event->element;
                    SsMembership::getInstance()->subscription->initSubscription( $request, $user );                    
                    
                }
            }
        });

        if ( Yii::$app->db->schema->getTableSchema('ssmembership_paymentgateway') != null ) {            
            $gateway = SsMembership::getInstance()->paymentGateway->getPaymentGateway();
            if( $gateway ) {
                if( $gateway->liveMode ) {
                    self::$STRIPE_KEY    = $gateway->livePublicKey;
                    self::$STRIPE_SECRET = $gateway->liveSecretKey;
                } else {
                    self::$STRIPE_KEY    = $gateway->testPublicKey;
                    self::$STRIPE_SECRET = $gateway->testSecretKey;
                }
            } 
        }
        
        // Do something after we're installed..
        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function (PluginEvent $event) {
                if ( $event->plugin === $this ) {
                }
            }
        );

        Craft::info(
            Craft::t(
                'ss-membership',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    /**
     * Creates and returns the model used to store the plugin’s settings.
     *
     * @return \craft\base\Model|null
     */
    protected function createSettingsModel()
    {
        return new Settings();
    }
    
    /**
     * Returns the rendered settings HTML, which will be inserted into the content
     * block on the settings page.
     *
     * @return string The rendered settings HTML
     */
    protected function settingsHtml(): string
    {
        return Craft::$app->view->renderTemplate(
            'ss-membership/settings',
            [
                'settings' => $this->getSettings()
            ]
        );
    }

    private function getSiteUrlRules()
    {
        return [
            'ss-membership/event' =>
                'ss-membership/webhook/index',
        ];
    }
}
