import { decryptPrivateKeySafe } from "./crypto.mjs";
import { TOKENS } from "../shared/tokens.mjs";
import path from "path";
import fs from "fs";
import { createTronWebWithPrivateKey } from "../shared/tronWebClient.mjs";

const logFile = path.resolve("./send_logs.log");

function logToFile(...args) {
    const timestamp = new Date().toISOString();
    const message = args
        .map(a => (typeof a === "object" ? JSON.stringify(a, null, 2) : a))
        .join(" ");
    fs.appendFileSync(logFile, `[${timestamp}] ${message}\n`);
}

export async function sendTransaction(token, body) {
    token = token.toUpperCase();

    logToFile("sendTransaction", token, body);

    const { privateKey: encrypted_private_key, to, amount } = body;
    const privateKey = decryptPrivateKeySafe(encrypted_private_key);

    const tronWeb = createTronWebWithPrivateKey(privateKey);

    logToFile("tronWeb created", tronWeb.defaultAddress.base58);

    if (token === "TRX") {
        return await tronWeb.trx.sendTransaction(
            to,
            Math.floor(Number(amount) * 1e6)
        );
    }

    const tokenInfo = TOKENS[token];
    if (!tokenInfo) throw new Error("Unknown token");

    logToFile(
        "SENDING FROM",
        tronWeb.defaultAddress.base58,
        "TO",
        to,
        "AMOUNT",
        amount
    );

    const contract = await tronWeb.contract().at(tokenInfo.address);

    return await contract.methods
        .transfer(
            to,
            Math.floor(Number(amount) * 10 ** tokenInfo.decimals)
        )
        .send({
            feeLimit: 100_000_000,
        });
}
