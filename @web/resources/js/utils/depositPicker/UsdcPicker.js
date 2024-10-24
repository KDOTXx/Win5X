import DepositPicker from "./DepositPicker.js";
import { queue } from "./DepositPickerManager.js";

export default class UsdcPicker extends DepositPicker {

  constructor(instanceId, id) {
    super();
    this.instanceId = instanceId;
    this._id = id;
  }

  id() {
    return this._id;
  }

  verify(address) {
    if(!queue('ethereum').next(this.instanceId)) {
      console.log(this.logName(), 'Skipped (queue)')
      return;
    }

    this.post('https://api-goerli.etherscan.io/api?module=account&action=tokentx' + '&startblock=0&endblock=999999999&address=' +
     address + '&sort=asc&apikey=ZU3TI9X5BYXAUUBK3E9ENZD595P9R9TCRR', {}).then(data => {
      console.log(this.logName(), data);
      try {
        if(typeof data.result === 'string') return;

        data.result.forEach(e => {
          if (!this.shouldSkipTx(e.hash))
            this.sendTx(e.hash);
        });
      } catch (e) {
        throw new Error(e);
      }
    }).catch((e) => {
      console.error(this.logName(), e);
    });
  }

}
