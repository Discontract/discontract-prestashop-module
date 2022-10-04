<!-- Block discontractpro -->
<div id="discontractpro_block_home" class="block">
  <div class="discontract-header">
    <input value="yes" type="checkbox" id="discontract-job-check" name="discontract_job_enabled"/>
    <h4>{l s='Meistro paslauga' mod='discontractpro'}</h4>
  </div>
  <div class="discontract-widget">
    <input type="hidden" id="discontract_cart" name="discontract_cart" />
    {foreach from=$jobs item=job}
      <div class="discontract-job">
        <input type="radio" class="disc-radio-choice" {$job.selected} name="id_discontract_job" value="{$job.job_id}">
        <span class="disc-price-block">
          <input type="hidden" class="discontract_product_id" name="discontract_product_id" value="{$job.discontract_product_id}" />
          <span class="discontract-price">{$job.job_price}{$job.job_currency}</span><span class="discontract-asterisk">*</span> - </span>
        {$job.title}
      </div>
    {/foreach}
    <div id="disc-explanation-block">*kaina gali kisti nuo pasirinkto atvykimo adreso</div>
    <div id="disc-postcode-block">
      <div class="disc-postcode-container">
        <input autocomplete="off" id="disc-postcode-input" placeholder="Įveskite adresą arba pašto kodą kainos apskaičiavimui" type="text" name="post_code" />
        <i id="discontract-loader" class="fa fa-circle-o-notch fa-spin fa-fw spinner-icon"></i>
      </div>
      <ul id="disc-postcode-autocomplete">
      </ul>
    </div>
  </div>
</div>
<script type="text/javascript">
  const DISCONTRACT_MODULE_LINK = "{$link->getModuleLink('discontractpro', 'discontractAjax', $ajax_params)}";
  const DISCONTRACT_PRODUCT_ID = {$product_id};
  const DISCONTRACT_PRODUCT_NAME = "{$product_name_real}";
</script>
<!-- /Block discontractpro -->