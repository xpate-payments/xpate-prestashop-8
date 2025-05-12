<style>
a.xpatecreditcard::after {
      display: block;
      content: "\f054";
      position: absolute;
      right: 15px;
      margin-top: -11px;
      top: 50%;
      font-family: "FontAwesome";
      font-size: 25px;
      height: 22px;
      width: 14px;
      color: #777; 
}
a.xpatecreditcard {
      background: url({$base_dir}modules/xpatecreditcard/logo_bestelling.png) 15px 12px no-repeat
}
</style>
<div class="row">
      <div class="col-xs-12">
            <p class="payment_module">
                  <a class="xpatecreditcard" href="{$link->getModuleLink('xpatecreditcard', 'payment')|escape:'html'}" title="{l s='Pay by Mastercard, VISA, Maestro or V PAY' d='Modules.Xpatecreditcard.Payment'}">
                        {l s='Pay by Mastercard, VISA, Maestro or V PAY' d='Modules.Xpatecreditcard.Payment'}</span>
                  </a>
            </p>
      </div>
</div>