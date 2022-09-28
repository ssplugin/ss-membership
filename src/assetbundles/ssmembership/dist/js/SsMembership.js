/**
 * SsMembership plugin for Craft CMS
 *
 * SsMembership JS
 *
 * @author    ssplugin
 * @copyright Copyright (c) 2021 ssplugin
 * @link      http://www.systemseeders.com/
 * @package   SsMembership
 * @since     1.0.0
 */
 
var elementStyles = {
    base: {
        color: '#32325D',
        fontWeight: 500,
        fontFamily: 'Lato, sans-serif',
        fontSize: '16px',
        fontSmoothing: 'antialiased',

        '::placeholder': {
            color: '#595858',
        },
        ':-webkit-autofill': {
            color: '#e39f48',
        },
    },
    invalid: {
        color: '#E25950',
        '::placeholder': {
            color: '#FFCCA5',
        },
    },
};
var elementClasses = {
    base: 'StripeElement',
    complete: 'StripeElement--complete',
    empty: 'StripeElement--empty',
    focus: 'StripeElement--focus',
    invalid: 'StripeElement--invalid',
    webkitAutoFill: 'StripeElement--webkit-autofill'
}; 

var subscribeForm = document.getElementById( 'ss-membership-form' );
if( subscribeForm !== null ) {
    var public_key = subscribeForm.elements[ 'stripe_public_key' ].value;
    if( public_key != '' ) {
        var stripe = Stripe( public_key );
        var elements = stripe.elements();

        var card = elements.create('card', {style: elementStyles,
            classes: elementClasses, hidePostalCode: true});
        card.mount('#card-element');

        card.on('change', function(event) {
            setOutcome(event);
        });

        subscribeForm.addEventListener( 'submit', function( event ) {
            event.preventDefault();
            const inputs = subscribeForm.elements;
            var cardName = '';
            if( inputs[ 'firstName' ].value == '' || inputs[ 'lastName' ].value == '' ) {
                cardName = inputs[ 'username' ].value;
            } else {
                cardName = inputs[ 'firstName' ].value + ' ' + inputs[ 'lastName' ].value;
            }

            stripe.createPaymentMethod({
                type: 'card',
                card: card,
                billing_details: {
                    name: cardName.trim(),
                    email: inputs[ 'email' ].value,
                },
            }).then(function(result) {
                if (result.error) {
                    setOutcome(result);
                } else {
                    // Send the token to your server.
                    stripeTokenHandler( result.paymentMethod );
                }
            });
        });
    }
}

function stripeTokenHandler( paymentMethod ) {
    // Insert the token ID into the form so it gets submitted to the server
    var subscribeForm = document.getElementById( 'ss-membership-form' );
    var hiddenInput = document.createElement( 'input' );
    hiddenInput.setAttribute( 'type', 'hidden' );
    hiddenInput.setAttribute( 'name', 'stripeToken' );
    hiddenInput.setAttribute( 'value', paymentMethod.id );
    subscribeForm.appendChild( hiddenInput );

    // Submit the form
    subscribeForm.submit();
}

function setOutcome(result) {
    var displayError = document.getElementById('card_errors');
    if (result.error) {
        displayError.textContent = result.error.message;
    } else {
        displayError.textContent = '';
    }
}
