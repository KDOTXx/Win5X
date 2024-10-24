import Vue from 'vue';

Vue.mixin({
    methods: {
        formatCurrency(value) {
            value = parseFloat(value);
            if(value == 0) return 0;
            if(value >= 10000) {
                value = Math.floor(value);
                if(~~(value / 100) % 10 + ~~(value / 10) % 10 === 0) return value / 1000 + 'B';
                return '' + ~~(value / 10000) + ~~(value / 1000) % 10 + '.' + ~~(value / 100) % 10 + ~~(value / 10) % 10 + 'B'
            }
            else if(value >= 1000) {
                value = Math.floor(value);
                if(~~(value / 100) % 10 + ~~(value / 10) % 10 === 0) return value / 1000 + 'B';
                return '' + ~~(value / 1000) + '.' + ~~(value / 100) % 10 + ~~(value / 10) % 10 + 'B';
            }
            else if(value >= 1) {
                if(Math.floor(value) == value) return Math.floor(value) + 'M'
                return value.toFixed(2) + 'M';
            }
            else {
                return parseInt(1000*value) + 'K';
            }
        }
    }
});