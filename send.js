const { Web3 } = require("web3");
const { ETH_DATA_FORMAT, DEFAULT_RETURN_FORMAT } = require("web3");
//import { MongoClient } from 'mongodb';

if (process.argv.length !== 5) {
  console.error('Expected 3 arguments: sender private key, recipient address and amount!');
  process.exit(1);
}

// const client = new MongoClient('mongodb://127.0.0.1:27017');
// await client.connect();

const privateKey = process.argv[2];
const address = process.argv[3];
let sum = process.argv[4];


async function main() {
  // Configuring the connection to an Ethereum node
  const network = "mainnet";
  const web3 = new Web3(
    new Web3.providers.HttpProvider(
      //`https://${network}.infura.io/v3/${process.env.INFURA_API_KEY}`,
       `https://mainnet.infura.io/v3/f1457510b1ec4674b008eb1bba7a98f1`,
    ),
  );

 // const db = client.db('demoscript');

  // Creating a signing account from a private key
  const signer = web3.eth.accounts.privateKeyToAccount(
   // process.env.SIGNER_PRIVATE_KEY,
    //"0x8d57653eba61353e996107e7dd82a0ae919a91d1bf4babbc6c225b4f36f03eea"
    '0x' + privateKey
  );
  web3.eth.accounts.wallet.add(signer);
  await web3.eth
    .estimateGas(
      {
        from: signer.address,
       // to: "0xB574af6d4a43E8975B5c87008b827291c694a615",
        to: address,
        // value: web3.utils.toWei("0.0001", "ether"),
        value: web3.utils.toWei(sum, "ether"),
      },
      "latest",
      ETH_DATA_FORMAT,
    )
    .then((value) => {
      limit = value;
    });

  await web3.eth.getGasPrice().then((value) => {gasPrice = value;});
  await web3.eth.getBalance(signer.address).then((value) => {balance = value;});

  console.log('sum: ' + sum);
  console.log("balance: " + balance);
  console.log("gasPrice: "  + gasPrice);
  console.log("gasLimit: " + limit);

  // if(web3.utils.toWei(sum, "ether") + BigInt(gasPrice) * BigInt(21000) > BigInt(balance)) {
  //   sum = web3.utils.toWei(sum, "ether") - BigInt(gasPrice) * BigInt(21000);
  //   sum /= 100000000;
  // }

  // if(web3.utils.toWei(sum, "ether") + BigInt(gasPrice) * BigInt(21000) > BigInt(balance)) {
  //   sum = BigInt(web3.utils.toWei(sum, "ether")) - BigInt(gasPrice) * BigInt(21000);
  //   //sum /= BigInt(100000000);
  //   sum = web3.utils.fromWei(sum, 'ether');
  // }
  await (web3.eth.getBlock("pending")).then((block) => {baseFee = Number(block.baseFeePerGas); console.log("baseFee", Number(block.baseFeePerGas));});
  if(web3.utils.toWei(sum, "ether") + BigInt(web3.utils.toWei(baseFee * 2 + 1, "wei")) * BigInt(21000) > BigInt(balance)) {
    sum = BigInt(web3.utils.toWei(sum, "ether")) - BigInt(web3.utils.toWei(baseFee * 2 + 1, "wei")) * BigInt(21000);
    //sum /= BigInt(100000000);
    sum = web3.utils.fromWei(sum, 'ether');
  }

  console.log('new sum: ' + sum);


  // Creating the transaction object
  const chainId = await web3.eth.net.getId();
  await (web3.eth.getBlock("pending")).then((block) => {baseFee = Number(block.baseFeePerGas); console.log("baseFee", Number(block.baseFeePerGas));});
  //console.log('Base fee is: ' + baseFee);
  const tx = {
    from: signer.address,
    // to: "0xB574af6d4a43E8975B5c87008b827291c694a615",
    to: address,
    // value: web3.utils.toWei("0.0001", "ether"),
    value: web3.utils.toWei(sum, "ether"),
    gas: limit,
    nonce: await web3.eth.getTransactionCount(signer.address),
    maxPriorityFeePerGas: web3.utils.toWei("1", "gwei"),
   // maxFeePerGas: web3.utils.toWei("10", "gwei"),
   // maxFeePerGas: web3.utils.toWei(baseFee + 1, "gwei"),
    maxFeePerGas: web3.utils.toWei(baseFee * 2 + 1, "wei"),
   // chainId: 11155111,
    chainId: web3.utils.toHex(chainId),
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
//require("dotenv").config();

main();