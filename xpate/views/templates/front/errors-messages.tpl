<h1>{l s='Your order at %s' sprintf=[$shop.name] mod='emspay'}</h1>

<h1>{l s='Unexpected payment error' mod='emspay'}</h1>

<div class="error">
    <p><b>{l s='Unfortunately there was a problem processing your payment.' mod='emspay'}</b></p>
    <p><a href="{$checkout_url}">{l s='Please click here to try again.' mod='emspay'}</a></p>
</div>
