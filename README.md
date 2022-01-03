# SsMembership plugin for Craft CMS 3.x

A membership site lets you limit access to your site’s content to only paid users.
<p>SS membership plugin will easily integrate with Stripe so you can accept membership payments and protect your content from non subscribed users. It will help you to setup membership integration of your site. 
                                    </p>
To read plugin documentation <a target="_blank" href="https://datadazzle.com/ssplugin/"> SsMembership Documentation</a> 

## Requirements

This plugin requires Craft CMS 3.0.0-beta.23 or later.

## Installation

To install the plugin, follow these instructions.

- In the Control Panel, go to Settings → Plugins and click the “Install” button for SsMembership.

## SsMembership Overview

It is work with a Craft cms User groups and permissions. You must be create a relevant groups and assign the permissions to each group. i.e. if your website need to setup <strong>Super</strong> and <strong>Premium</strong> membership, you need to create User Groups like Super and Premium. Now, create a membership plan and assign user group with it.

## Configuring SsMembership

Once you’ve installed the SS Membership plugin. Configure Stripe gateway public and secret keys and save the configuration.

<h4>Membership Plan</h4>
<p>Membership Plan allow manage user groups for when a user subscribes to a plan. On your Craft CMS Dashboard go to <code>Settings -> Membership Plan</code></p>
<p>Plugin will automatically create a subscription plan on Stripe Dashboard.</p>
<p>Test mode plans no longer availanle in live mode so that test and live mode membership plans are different.</p>

## Using SsMembership

Check permission on twig template, you can use Craft cms core functions to check specific group permission. Here are few examples.

```

{% if currentUser.isInGroup('groupHandle') %}
     You are allowed to access this content.
{% endif %}

```
<code>.can()</code> method is helpful when you need to check specific permission or craft cms section permission.

```
{# example 1 #}
{% if currentUser.can('permissionName') %}
     You are allowed to access this content.
{% endif %}

{# example 2 #}
{% if currentUser.can("createEntries:#{section.uid}") %}
     You are allowed to Create a new section. 
{% endif %}
```

## Twig Templating

<h4>Subscribing with User Registration</h4>
While user registration, add stripe card payment and membership plan dropdown field. User will automatically subscribed selected plan after successfully register.

User Registration Form with membership field:

<h6>Stripe Payment Field:</h6>

```
{{ craft.ssMembership.paymentField }}
```

<h6>Membership Plan Field:</h6>

```
{{ craft.ssMembership.planField }}
```
<h4>Subscribe with logged in User:</h4>

We have understood how subcription work with registration But what if user already registered? User can subscribe membership plan after logged in.

Note, if logged in User have already subscribed any of the subcription plan then not able to subscribe other membership plan.

```
{# Make sure user is logged in #}
{% requireLogin %}
{% set plans = craft.ssMembership.getplan() %}
<form method="post">
    {{ csrfInput() }}
    {{ hiddenInput( 'action', 'ss-membership/subscription/switch' ) }}
    <select name="planUid" class="">
        <option value=""> Select Plan </option>
        {% for plan in plans %}
            <option value="{{ plan.uid|hash }}"> {{ plan.name }} </option>
        {% endfor %}
    </select>

    {# stripe card payment #}
    {{ craft.ssMembership.paymentField() }}

    <button type="submit" class="button link"> Subscribe </button>
</form>
```

<h4>Cancel subscription:</h4>

Cancel user's subscription immediately. The customer will not be charged again for the subscription.

Note, however, that any pending invoice items that you’ve created will still be charged for at the end of the period, unless manually deleted. If you’ve set the subscription to cancel at the end of the period, any pending prorations will also be left in place and collected at the end of the period. But if the subscription is set to cancel immediately, pending prorations will be removed.

For cancel current subscription of logged in User {% requireLogin %}

Canceled subscription can\`t be reactivate again.

```
{# Make sure user is logged in #}
{% requireLogin %}                                        
{% set subscription = craft.ssMembershipSubscription.getSubscription() %}
{% if subscription is not empty %}
    <form method="post">
        {{ csrfInput() }}
        {{ hiddenInput( 'action', 'ss-membership/subscription/cancel' ) }}
        {{ hiddenInput( 'subUid', subscription.uid|hash ) }}        
        <select name="cancelType" class="">
            <option value="immediately"> Immediately </option>
            <option value="period_end"> Cancel at period end </option>
        </select>        
        <button type="submit" class="button link"> Cancel </button>
    </form>
{% endif %}

```

Brought to you by [ssplugin](http://www.systemseeders.com/)
