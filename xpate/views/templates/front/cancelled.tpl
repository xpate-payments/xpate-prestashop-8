{extends file=$template}

{block name='content'}
    
<h1>{l s='Your order at %s' sprintf=[$shop.name] d='Modules.Xpate.Cancelled'}</h1>

<div class="error">
    <p><b>{l s='Unfortunately, we can not currently accept your purchase with this payment method. Please choose another payment option to complete your order.' d='Modules.Xpate.Cancelled'}</b></p>
    {if isset($error_message)}
        <p><strong>{$error_message}</strong></p>
    {/if}
    <p><a href="{$checkout_url}">{l s='Please click here to try again.' d='Modules.Xpate.Cancelled'}</a></p>
</div>

{/block}