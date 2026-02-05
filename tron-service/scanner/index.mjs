// tronScanner.js
import {getTronWeb} from "../shared/tronWebClient.mjs";
import path from "path";
import fs from "fs";
import crypto from "crypto";
import dotenv from "dotenv";
import { TOKENS } from "../shared/tokens.mjs";
import {fetchWalletsAndSave} from "./saveWallets.mjs" // <-- убедись, что файл есть


// -----------------------------
// ENV
// -----------------------------
dotenv.config({ path: path.resolve(process.cwd(), "../.env") });

const APP_URL = process.env.APP_URL;
const WEBHOOK_URL = `${APP_URL}/api/crypto/webhook`;
const WEBHOOK_SECRET = process.env.WEBHOOK_SECRET;

const SCAN_INTERVAL = 30_000;           // 30 секунд для сканирования блоков
const WALLET_SYNC_INTERVAL = 10 * 60_000; // 10 минут для обновления кошельков

// -----------------------------
// LOGGING
// -----------------------------
const logFile = path.resolve("./scan_logs.log");

function logToFile(...args) {
    try {
        const timestamp = new Date().toISOString();
        const message = args.map(a => (typeof a === "object" ? JSON.stringify(a, null, 2) : a)).join(" ");
        fs.appendFileSync(logFile, `[${timestamp}] ${message}\n`);
    } catch (err) {
        console.error("Logging failed:", err);
    }
}

logToFile("TRON scanner started");

// -----------------------------
// TronWeb
// -----------------------------
const tronWeb = getTronWeb();

// -----------------------------
// WATCHED WALLETS
// -----------------------------

let WATCHED_WALLETS = new Set();
const WALLETS_FILE = path.resolve("./tron_wallets.json");

async function syncWallets() {
    try {
        if (!fs.existsSync(WALLETS_FILE)) {
            logToFile("Wallets file not found, skipping sync");
            fetchWalletsAndSave();
            return;
        }

        const walletsData = fs.readFileSync(WALLETS_FILE, "utf8");
        const wallets = JSON.parse(walletsData);

        WATCHED_WALLETS = new Set(wallets.map(w => w.toLowerCase()));
        logToFile(`Wallets synced: ${WATCHED_WALLETS.size}`);
    } catch (err) {
        logToFile("Wallet sync error:", err.message);
    }
}

// -----------------------------
// WEBHOOK
// -----------------------------
async function sendWebhook(payload) {
    try {
        const body = JSON.stringify(payload);
        const signature = crypto.createHmac("sha256", WEBHOOK_SECRET).update(body).digest("hex");

        const https = await import("https");
        const url = new URL(WEBHOOK_URL);

        const options = {
            hostname: url.hostname,
            port: url.port || 443,
            path: url.pathname,
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-Signature": signature,
                "Content-Length": Buffer.byteLength(body)
            }
        };

        const req = https.request(options, res => {
            logToFile(`Webhook sent: ${payload.type}, status ${res.statusCode}`);
        });

        req.on("error", err => {
            logToFile("Webhook error:", err.message);
        });

        req.write(body);
        req.end();
    } catch (e) {
        logToFile("Webhook exception:", e.message);
    }
}

// -----------------------------
// SCANNER
// -----------------------------
let lastBlock = 0;

let scanning = false;

async function scanBlocks() {
    if (scanning) return;
    scanning = true;

    let processedUpTo = lastBlock;

    try {
        if (!tronWeb || WATCHED_WALLETS.size === 0) {
            return;
        }

        const currentBlock = await tronWeb.trx.getCurrentBlock();
        const current = currentBlock.block_header.raw_data.number;

        if (lastBlock === 0) {
            lastBlock = current - 1;
            processedUpTo = lastBlock;
        }

        for (let n = lastBlock + 1; n <= current; n++) {
            let block;

            try {
                block = await tronWeb.trx.getBlock(n);
            } catch (e) {
                logToFile(`Block ${n} fetch error: ${e.message}`);
                break;
            }

            if (!block?.transactions) {
                processedUpTo = n;
                continue;
            }

            for (const tx of block.transactions) {
                try {
                    const contract = tx.raw_data?.contract?.[0];
                    if (!contract) continue;

                    const value = contract.parameter.value;

                    /* ---------- TRX ---------- */
                    if (contract.type === "TransferContract") {
                        const from = tronWeb.address.fromHex(value.owner_address);
                        const to = tronWeb.address.fromHex(value.to_address);

                        if (
                            !WATCHED_WALLETS.has(from.toLowerCase()) &&
                            !WATCHED_WALLETS.has(to.toLowerCase())
                        ) continue;

                        sendWebhook({
                            txid: tx.txID,
                            type: "TRX",
                            from,
                            to,
                            amount: Number(value.amount) / 1e6,
                            block: n
                        }).catch(e =>
                            logToFile(`Webhook TRX error: ${e.message}`)
                        );
                    }

                    /* ---------- TRC20 ---------- */
                    if (contract.type === "TriggerSmartContract") {
                        const contractAddress = tronWeb.address.fromHex(
                            value.contract_address
                        );

                        const symbol = Object.keys(TOKENS).find(
                            k =>
                                tronWeb.address.toHex(TOKENS[k].address) ===
                                tronWeb.address.toHex(contractAddress)
                        );
                        if (!symbol) continue;

                        const inputData = value.data;
                        if (!inputData || !inputData.startsWith("a9059cbb")) continue;

                        const from = tronWeb.address.fromHex(value.owner_address);
                        const toHex = "41" + inputData.slice(32, 72);
                        const to = tronWeb.address.fromHex(toHex);

                        if (
                            !WATCHED_WALLETS.has(from.toLowerCase()) &&
                            !WATCHED_WALLETS.has(to.toLowerCase())
                        ) continue;

                        const rawAmount = BigInt("0x" + inputData.slice(72, 136));
                        const amount =
                            Number(rawAmount) / 10 ** TOKENS[symbol].decimals;

                        sendWebhook({
                            txid: tx.txID,
                            type: symbol,
                            from,
                            to,
                            amount,
                            block: n
                        }).catch(e =>
                            logToFile(`Webhook ${symbol} error: ${e.message}`)
                        );
                    }
                } catch (txErr) {
                    logToFile(`TX ${tx.txID} error: ${txErr.message}`);
                }
            }

            processedUpTo = n;
        }

        lastBlock = processedUpTo;
        logToFile(`Scan completed up to block ${lastBlock}`);
    } catch (err) {
        logToFile(`Scanner fatal error: ${err.message}`);
    } finally {
        scanning = false;
        setTimeout(scanBlocks, SCAN_INTERVAL);
    }
}


// -----------------------------
// START
// -----------------------------
(async () => {
    logToFile("TRON scanner starting full initialization...");

    // 1️⃣ Создаем/обновляем файл кошельков перед сканером
    await fetchWalletsAndSave();
    logToFile("Wallets saved to file");

    // 2️⃣ Загружаем кошельки в память
    await syncWallets();
    logToFile(`Wallets synced: ${WATCHED_WALLETS.size}`);

    // 3️⃣ Таймер на обновление кошельков каждые 10 минут
    setInterval(async () => {
        await fetchWalletsAndSave();
        logToFile("Wallets refreshed via timer");
    }, WALLET_SYNC_INTERVAL);

    // 4️⃣ Запуск сканирования блоков каждые 30 секунд
    scanBlocks();
})();
