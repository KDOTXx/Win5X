const { Web3 } = require("web3");
const { ETH_DATA_FORMAT, DEFAULT_RETURN_FORMAT } = require("web3");

if (process.argv.length !== 5) {
  console.error('Expected 3 arguments: sender private key, recipient address and amount!');
  process.exit(1);
}

const privateKey = process.argv[2];
const address = process.argv[3];
let sum = process.argv[4];

console.log(sum);


async function main() {
  // Configuring the connection to an Ethereum node
  const network = "sepolia";
  const web3 = new Web3(
    new Web3.providers.HttpProvider(
       `https://sepolia.infura.io/v3/f1457510b1ec4674b008eb1bba7a98f1`,
    ),
  );

  var fs = require('fs');
  var jsonFile = "storage/erc20.json";

  var abi=JSON.parse(fs.readFileSync(jsonFile));
  // console.log(parsed);
  // var abi = parsed.abi;
  // console.log(abi);

  const tokenAddress = '0x779877A7B0D9E8603169DdbD7836e478b4624789';

  // Creating a signing account from a private key
  const signer = web3.eth.accounts.privateKeyToAccount(
    '0x' + privateKey
  );
  web3.eth.accounts.wallet.add(signer);

  const contract = new web3.eth.Contract(abi, tokenAddress, { from: signer.address } );
 // let amount = web3.utils.toHex(web3.utils.toWei(sum, 'ether')); 
  let amount = web3.utils.toWei(sum,'ether');
  console.log(amount);

  // await web3.eth
  //   .estimateGas(
  //     {
  //       from: signer.address,
  //       to: address,
  //       value: web3.utils.toWei(sum, "ether"),
  //     },
  //     "latest",
  //     ETH_DATA_FORMAT,
  //   )
  //   .then((value) => {
  //     limit = value;
  //   });

  // await web3.eth.getGasPrice().then((value) => {gasPrice = value;});
  // await web3.eth.getBalance(signer.address).then((value) => {balance = value;});

  // console.log('sum: ' + sum);
  // console.log("balance: " + balance);
  // console.log("gasPrice: "  + gasPrice);
  // console.log("gasLimit: " + limit);

  // await (web3.eth.getBlock("pending")).then((block) => {baseFee = Number(block.baseFeePerGas); console.log("baseFee", Number(block.baseFeePerGas));});
  // if(web3.utils.toWei(sum, "ether") + BigInt(web3.utils.toWei(baseFee * 2 + 1, "wei")) * BigInt(21000) > BigInt(balance)) {
  //   sum = BigInt(web3.utils.toWei(sum, "ether")) - BigInt(web3.utils.toWei(baseFee * 2 + 1, "wei")) * BigInt(21000);
  //   sum = web3.utils.fromWei(sum, 'ether');
  // }

  // console.log('new sum: ' + sum);


  // Creating the transaction object
  // const chainId = await web3.eth.net.getId();
   await (web3.eth.getBlock("pending")).then((block) => {baseFee = Math.round(Number(block.baseFeePerGas)/1000000000); console.log("baseFee", baseFee);});
   console.log("maxFeePerGas:" + baseFee*1.2);
   console.log("2 gwei u wei: " + web3.utils.toWei(2, 'gwei'));
   console.log("maxFeePerGas pretvoren u wei: " + web3.utils.toWei(Math.round(baseFee * 1.2).toString(), "gwei"));
   console.log("maxFeePerGas bez roundanja vrijednosti: " +  web3.utils.toWei((baseFee * 1.2).toString(), "gwei"));
  // console.log(contract.methods.transfer(address, amount));
   var price = Math.round(baseFee * 1.2) * 50000 * 1000000000;
   //console.log(web3.utils.toWei(baseFee * 1.2, "gwei"));
   console.log("Ukupna cijena transakcije: "+price);
  // exit();
  //console.log('Base fee is: ' + baseFee);
  const tx = {
    from: signer.address,
    //to: address,
    to: '0x779877A7B0D9E8603169DdbD7836e478b4624789',
    //value: web3.utils.toWei(sum, "ether"),
    value: 0,
    data: contract.methods.transfer(address, BigInt(sum) * web3.utils.toNumber("1000000000000000000")).encodeABI(),
    //gas: web3.utils.toHex(45000),
    gas: 65000,
    nonce: await web3.eth.getTransactionCount(signer.address),
   // maxPriorityFeePerGas: web3.utils.toHex(web3.utils.toWei('2', 'gwei')),
    maxPriorityFeePerGas: web3.utils.toWei(2, 'gwei'),
 //   gasPrice: await web3.eth.getGasPrice(),
    maxFeePerGas: web3.utils.toWei(Math.round(baseFee * 1.5).toString(), "gwei"),
   // maxFeePerGas: web3.utils.toHex(baseFee),
    chainId: 11155111,
   // chain: "goerli",
  //  hardfork: "london",
    type: 0x2,
  };
  signedTx = await web3.eth.accounts.signTransaction(tx, signer.privateKey);
  console.log("Raw transaction data: " + signedTx.rawTransaction);
  // Sending the transaction to the network
  const receipt = await web3.eth
    .sendSignedTransaction(signedTx.rawTransaction)
    .once("transactionHash", (txhash) => {
      console.log(`Mining transaction ...`);
      console.log(`https://${network}.etherscan.io/tx/${txhash}`);
    });
  // The transaction is now on chain!
  console.log(`Mined in block ${receipt.blockNumber}`);
}

main();