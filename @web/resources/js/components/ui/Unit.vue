<template>
  <span class="unit">{{ computedValue }}</span>
</template>

<script>
  import bitcoin from 'bitcoin-units';
  import {mapGetters} from 'vuex';
  // import numberFormatter from "../mixins/numberFormatter.js";

  export default {
    computed: {
      computedValue() {
        // if (this.to.startsWith('local_')) return (this.value ? this.value : 0).toFixed(2);
        //console.log(this.formatCurrency(this.value));
        if(this.to.startsWith('local_')) return this.formatCurrency(this.value);
        if (this.fiat) return '$' + this.tokenToFiat(this.currencies[this.to].price, this.value ? this.value : 0).toFixed(2);

        return bitcoin(this.value ? this.value : 0, 'btc').to(this.unit).value().toFixed(this.unit === 'satoshi' ? 0 : 8);
      },
      ...mapGetters(['unit'])
    },
    props: ['value', 'to', 'fiat']
  }
</script>

<style lang="scss" scoped>
  .unit {
    margin-right: 5px;
  }
</style>
