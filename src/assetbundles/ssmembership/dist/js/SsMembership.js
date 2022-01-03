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
 
var stripe = Stripe('pk_test_YUZ8G5Eb5UyV7yDOXCMk4g2J');
var elements = stripe.elements();
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

var card = elements.create('card', {style: elementStyles,
    classes: elementClasses, hidePostalCode: true});
card.mount('#card-element');

card.on('change', function(event) {
    setOutcome(event);
});

document.querySelector( 'form' ).addEventListener( 'submit', function( e ) {
    e.preventDefault();

    var paymentData = {
        billing_details: {
            name: document.getElementsByName( 'username' ).value,
            email: document.getElementsByName( 'email' ).value,
        }
    };

    stripe.createPaymentMethod( 'card', card, paymentData ).then( setOutcome );
    // stripe.confirmCardPayment( 'card', card, options ).then( setOutcome );
});

function setOutcome(result) {
    if (result.error) {
        var displayError = document.getElementById('card_errors');
        if (result.error) {
            displayError.textContent = result.error.message;
        } else {
            displayError.textContent = '';
        }
    } else if( result.paymentMethod ) {
        var form = document.querySelector( 'form' );
        var hiddenInput = document.createElement( 'input' );
        hiddenInput.setAttribute( 'type', 'hidden' );
        hiddenInput.setAttribute( 'name', 'stripeToken' );
        hiddenInput.setAttribute( 'value', result.paymentMethod.id );
        form.appendChild( hiddenInput );
        form.submit();
    }
}