{include file="$tpl_dir/errors.tpl"}

<h1>{l s='There was an error processing your order' d='Modules.Xpategooglepay.Error'}</h1>

<div class="error">
    <p><strong>{$error_message}</strong></p>
    <p>
        <a href="{$checkout_url}">
            {l s='Please click here to choose another payment method.' d='Modules.Xpategooglepay.Error'}
        </a>
    </p>
</div>

<a href="{$checkout_url}" title="{l s='Please click here to try again.' d='Modules.Xpategooglepay.Error'}" class="button-exclusive btn btn-default">
    <i class="icon-chevron-left"></i>
    {l s='Go back to the checkout page' d='Modules.Xpategooglepay.Error'}
</a>
