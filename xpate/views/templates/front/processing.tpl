{extends file=$template}

{block name='content'}
    <h1>
        {l s='Your order at %s' sprintf=[$shop.name] d='Modules.Xpate.Processing'}
    </h1>
    <h3>
        {l s='Please wait while your order status is being checked...' d='Modules.Xpate.Processing'}
    </h3>

    <div><img src="{$modules_dir}/xpate/ginger/assets/ajax-loader.gif"/></div>

    <script language="JavaScript">
        {literal}
            var fallback_url = '{/literal}{$fallback_url}{literal}';
            var validation_url = '{/literal}{$validation_url}{literal}';
        {/literal}
    </script>
    <script type="text/javascript" src="{$modules_dir}/xpate/ginger/processing.js"></script>
{/block}