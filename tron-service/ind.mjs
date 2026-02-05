import express from "express";
import cors from "cors";
import axios from "axios";
import { TronWeb } from "tronweb";
import dotenv from "dotenv";
import path from "path";
import crypto from "crypto";
import fs from "fs";
// Загружаем .env
dotenv.config({ path: path.resolve(process.cwd(), "../.env") });
const WEBHOOK_URL = ${process.env.APP_URL}/api/crypto/webhook;
const WEBHOOK_SECRET = process.env.WEBHOOK_SECRET;
// Логирование только для /send/:token
function logToFile(title, data) {
    const logLine = [${new Date().toISOString()}]
    ${title} ${JSON.stringify(data)}\n;
    fs.appendFileSync(path.resolve(process.cwd(), "send_token.log"), logLine); }
// App
const app = express();
app.use(express.json());
app.use(cors());
// TronWeb (Mainnet)
const tronWeb = new TronWeb({ fullHost: "https://api.trongrid.io" });
// TRC20 ABI
const TRC20_ABI = [ { constant: true, inputs: [{ name: "_owner", type: "address" }], name: "balanceOf", outputs: [{ name: "balance", type: "uint256" }], type: "function" } ];
// Tokens (Mainnet)
const TOKENS = { TRX: { address: null, decimals: 6 }, USDT: { address: "TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t", decimals: 6 }, USDC: { address: "TLZSucJRjnqBKwvQz6n5hd29gbS4P7u7w8", decimals: 6 } };
// ----------------------------- // Create wallet // -----------------------------
app.get("/create-wallet", async (req, res) => {
    try { const account = await tronWeb.createAccount(); res.json(account);
    }
    catch (e) { res.status(500).json({ error: e.message }); } });
// ----------------------------- // Send webhook // -----------------------------
async function sendWebhook(payloadObj)
{ try
    { const payload = JSON.stringify(payloadObj);
        const signature = crypto .createHmac("sha256", WEBHOOK_SECRET) .update(payload) .digest("hex");
        await axios.post(WEBHOOK_URL, payloadObj, { headers: { "X-Signature": signature, "Content-Type": "application/json" } });
    } catch (e)
    {
        // ошибки вебхука не логируем
    } }
        // ----------------------------- // Block scanner // -----------------------------
let lastBlock = 0;
async function scanBlocks() {
    try
    { const block = await tronWeb.trx.getCurrentBlock();
        const current = block.block_header.raw_data.number;
        if (lastBlock === 0)
            lastBlock = current - 1;
        for (let n = lastBlock + 1; n <= current; n++)
        { const b = await tronWeb.trx.getBlock(n);
            if (!b.transactions) continue;
            for (const tx of b.transactions) {
                const contract = tx.raw_data.contract[0];
            }
            const value = contract.parameter.value;
    }
        // TRX transfers
if (contract.type === "TransferContract")
{ await sendWebhook({ type: "TRX", txid: tx.txID, from: tronWeb.address.fromHex(value.owner_address), to: tronWeb.address.fromHex(value.to_address), amount: Number(value.amount) / 1e6, block: n }); }
// TRC20 transfers
if (contract.type === "TriggerSmartContract")
{ const contractAddress = tronWeb.address.fromHex(value.contract_address);
    const symbol = Object.keys(TOKENS).find( k => TOKENS[k].address && tronWeb.address.toHex(TOKENS[k].address) === tronWeb.address.toHex(contractAddress) );
    if (!symbol) continue;
    const inputData = value.data;
    const methodID = inputData.slice(0, 8);
    if (methodID === "a9059cbb")
    { const toHex = "41" + inputData.slice(8, 8 + 64).slice(24);
        const amount = parseInt(inputData.slice(8 + 64, 8 + 64 + 64), 16) / 10 ** TOKENS[symbol].decimals;
        await sendWebhook({ type: symbol, txid: tx.txID, from: tronWeb.address.fromHex(value.owner_address), to: tronWeb.address.fromHex(toHex), amount, block: n });
    }
}
    }
}
lastBlock = current;
}
catch (e) {

}
setTimeout(scanBlocks, 3000);
} scanBlocks();
// ----------------------------- // Send TRX // -----------------------------
app.post("/send/trx", async (req, res) => {
    try
    {
        const { privateKey, to, amount } = req.body;
        const tw = new TronWeb({ fullHost: "https://api.trongrid.io", privateKey });
        const tx = await tw.trx.sendTransaction(to, tronWeb.toSun(amount));
        res.json(tx);
    } catch (e)
    { res.status(500).json({ error: e.message });
    }
});
// ----------------------------- // Send TRC20 // -----------------------------
app.post("/send/:token", async (req, res) => {
    try
    {
        const { token } = req.params;
    }
    const { wallet_id, to, amount } = req.body;
    if (!wallet_id || !to || !amount)
    { return res.status(400).json({ error: "Missing params" });    }
    const wallet = WALLETS[wallet_id];
    if (!wallet)
    { return res.status(404).json({ error: "Wallet not found" }); }
    const tokenInfo = TOKENS[token.toUpperCase()];
    if (!tokenInfo) { return res.status(400).json({ error: "Unknown token" }); }
    }
    // 🔓 decrypt private key ONLY in memory
const privateKey = decrypt(wallet.encryptedPrivateKey);
const tw = new TronWeb({ fullHost: "https://api.trongrid.io", privateKey });
const sender = wallet.address;
// Gas check
const trxBalance = await tw.trx.getBalance(sender);
if (trxBalance < 100000)
{ return res.status(400).json({ error: "Insufficient TRX for fee" }); }
let txid;
if (token.toUpperCase() === "TRX")
{ const tx = await tw.trx.sendTransaction( to, tronWeb.toSun(amount) );
    txid = tx.txid || tx.txID; }
else
{ const contract = await tw.contract().at(tokenInfo.address);
    const tx = await contract.methods .transfer( to, Math.floor(amount * 10 ** tokenInfo.decimals) ) .send();
    txid = tx?.txID || tx?.transaction?.txID; }
res.json({ status: "ok", txid, from: sender, to, token: token.toUpperCase(), amount });
}
catch (e)
{ res.status(500).json({ error: "Transaction failed" });
}
});
// ----------------------------- // Get balances // -----------------------------
app.get("/wallet/:address/balances", async (req, res) =>
{ try
{ const { address } = req.params;
    if (!address) return res.status(400).json({ error: "Empty address" });
    const base58 = address.startsWith("41") ? tronWeb.address.fromHex(address) : address;
    const hex = tronWeb.address.toHex(base58);
    const balances = {};
    balances.TRX = (await tronWeb.trx.getBalance(base58)) / 1e6; for (const [symbol, token] of Object.entries(TOKENS))
    {
        if (!token.address) continue;
        try { const contract = await tronWeb.contract(TRC20_ABI, token.address);
            const balance = await contract.balanceOf(hex).call({ from: hex });
            balances[symbol] = Number(balance) / 10 ** token.decimals; }
        catch { balances[symbol] = 0; }
    } res.json({ address: base58, hex, balances });
} catch (e) { res.status(500).json({ error: e.message });
}
});
// ----------------------------- // Estimate fee dynamically // ----------------------------- //
// ----------------------------- // Estimate fee dynamically (with free bandwidth & frozen energy)
app.post("/estimate-fee", async (req, res) =>
{
    try
    { const { token, amount, from, to } = req.body;
        if (!token || !amount || !from || !to) { return res.status(400).json({ error: "Missing required parameters" });
        }
        const tokenInfo = TOKENS[token.toUpperCase()];
        if (!tokenInfo) return res.status(400).json({ error: "Unknown token" });
        const base58 = from.startsWith("41") ? tronWeb.address.fromHex(from) : from;
        const accountResource = await tronWeb.trx.getAccountResources(base58);
    }
    // Получаем цены сети
const chainParams = await tronWeb.trx.getChainParameters();
    const energyPrice = chainParams.find(p => p.key === 'getEnergyFee')?.value / 1_000_000 || 0.00028;
    const bandwidthPrice = chainParams.find(p => p.key === 'getTransactionFee')?.value / 1_000_000 || 0.000001;
    // free bandwidth
const freeBandwidth = accountResource.freeNetLimit - accountResource.freeNetUsed;
const freeEnergy = accountResource.EnergyLimit - accountResource.EnergyUsed;
let energyUsed = 0;
let bandwidthUsed = 0;
if (token === 'TRX') {
    // TRX transfer
    bandwidthUsed = 250;
    // стандартная транзакция TRX
    } else {
    // TRC20 transfer
    const contract = await tronWeb.contract().at(tokenInfo.address);
    const trigger = await tronWeb.transactionBuilder.triggerSmartContract( tokenInfo.address, 'transfer(address,uint256)',
        {}, [ { type: 'address', value: to },
            { type: 'uint256', value: Math.floor(amount * 10 ** tokenInfo.decimals) } ], base58 );
    energyUsed = trigger.energy_used || 15000;
    bandwidthUsed = trigger.transaction.raw_data_hex.length / 2;
}
// уменьшаем bandwidth на freeNetLimit
const paidBandwidth = Math.max(0, bandwidthUsed - (freeBandwidth || 0));
const paidEnergy = Math.max(0, energyUsed - (freeEnergy || 0));
const networkFeeTRX = paidBandwidth * bandwidthPrice + paidEnergy * energyPrice;
const serviceFeeTRX = 0.2;
configurable res.json({ network_fee: networkFeeTRX, service_fee: serviceFeeTRX,
    total_fee: networkFeeTRX + serviceFeeTRX, fee_currency: 'TRX', energy_used: energyUsed,
    bandwidth_used: bandwidthUsed, free_energy: freeEnergy, free_bandwidth: freeBandwidth });
} catch (e)
{ res.status(500).json({ error: e.message }); }
});
// -----------------------------
const PORT = process.env.PORT || 4000;
}
app.listen(PORT, () => console.log(✅ Tron Node service running on port ${PORT}));
