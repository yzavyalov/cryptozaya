import {
    JsonRpcProvider,
    formatEther,
    formatUnits,
    Interface,
    getAddress
} from "ethers";
import path from "path";
import fs from "fs";
import crypto from "crypto";
import dotenv from "dotenv";
import { TOKENS } from "../shared/tokens.mjs";
import { fetchWalletsAndSave } from "./saveWallets.mjs";
import {
    loadScannerState,
    saveScannerState,
    hasProcessedEvent,
    markEventProcessed,
    cleanupProcessedEvents
} from "./scannerState.mjs";

dotenv.config({ path: path.resolve(process.cwd(), ".env") });

const ETH_RPC_URL = process.env.ETH_RPC_URL;
const APP_URL = process.env.APP_URL;
const WEBHOOK_URL = `${APP_URL}/api/crypto/eth-webhook`;
const WEBHOOK_SECRET = process.env.WEBHOOK_SECRET;

if (!ETH_RPC_URL) {
    throw new Error("ETH_RPC_URL is not set in .env");
}

if (!APP_URL) {
    throw new Error("APP_URL is not set in .env");
}

if (!WEBHOOK_SECRET) {
    throw new Error("WEBHOOK_SECRET is not set in .env");
}

const SCAN_INTERVAL = 30_000;
const WALLET_SYNC_INTERVAL = 10 * 60_000;
const CONFIRMATION_DEPTH = 3;
const REORG_SAFETY_BLOCKS = 20;

const logFile = path.resolve(process.cwd(), "scan_logs.log");

function logToFile(...args) {
    try {
        const timestamp = new Date().toISOString();
        const message = args
            .map(a => (typeof a === "object" ? JSON.stringify(a, null, 2) : a))
            .join(" ");

        fs.appendFileSync(logFile, `[${timestamp}] ${message}\n`);
    } catch (err) {
        console.error("Logging failed:", err);
    }
}

logToFile("ETH scanner started");

const provider = new JsonRpcProvider(ETH_RPC_URL);

let WATCHED_WALLETS = new Set();
const WALLETS_FILE = path.resolve(process.cwd(), "eth_wallets.json");

async function syncWallets() {
    try {
        if (!fs.existsSync(WALLETS_FILE)) {
            logToFile("Wallets file not found, fetching wallets");
            await fetchWalletsAndSave();
        }

        const walletsData = fs.readFileSync(WALLETS_FILE, "utf8");
        const wallets = JSON.parse(walletsData);

        WATCHED_WALLETS = new Set(
            wallets.map(w => String(w).toLowerCase())
        );

        logToFile(`Wallets synced: ${WATCHED_WALLETS.size}`);
    } catch (err) {
        logToFile("Wallet sync error:", err.message);
    }
}

async function sendWebhook(payload) {
    return new Promise(async (resolve, reject) => {
        try {
            const body = JSON.stringify(payload);
            const signature = crypto
                .createHmac("sha256", WEBHOOK_SECRET)
                .update(body)
                .digest("hex");

            const http = await import("http");
            const https = await import("https");
            const url = new URL(WEBHOOK_URL);
            const client = url.protocol === "https:" ? https : http;

            const options = {
                hostname: url.hostname,
                port: url.port || (url.protocol === "https:" ? 443 : 80),
                path: url.pathname,
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-Signature": signature,
                    "Content-Length": Buffer.byteLength(body)
                }
            };

            const req = client.request(options, res => {
                let data = "";

                res.on("data", chunk => {
                    data += chunk;
                });

                res.on("end", () => {
                    logToFile(`Webhook sent: ${payload.type}, status ${res.statusCode}`);

                    if (res.statusCode >= 200 && res.statusCode < 300) {
                        resolve(true);
                    } else {
                        reject(new Error(`Webhook HTTP ${res.statusCode}: ${data}`));
                    }
                });
            });

            req.on("error", err => {
                logToFile("Webhook error:", err.message);
                reject(err);
            });

            req.write(body);
            req.end();
        } catch (e) {
            logToFile("Webhook exception:", e.message);
            reject(e);
        }
    });
}

const erc20Interface = new Interface([
    "event Transfer(address indexed from, address indexed to, uint256 value)"
]);

const TRANSFER_TOPIC = erc20Interface.getEvent("Transfer").topicHash;

let scanning = false;
let state = loadScannerState();

function normalizeAddress(address) {
    try {
        return getAddress(address).toLowerCase();
    } catch {
        return String(address).toLowerCase();
    }
}

function getTrackedTokenByAddress(address) {
    const normalized = normalizeAddress(address);

    return Object.entries(TOKENS).find(([, token]) => {
        return normalizeAddress(token.address) === normalized;
    });
}

async function processWebhook(payload, blockNumber) {
    if (hasProcessedEvent(state, payload)) {
        logToFile("Skipped duplicate event:", payload.txid, payload.type);
        return;
    }

    await sendWebhook(payload);

    markEventProcessed(state, payload, blockNumber);
    saveScannerState(state);
}

async function scanNativeTransfersInBlock(blockNumber) {
    const block = await provider.getBlock(blockNumber, true);

    if (!block?.transactions?.length) {
        return;
    }

    for (const tx of block.transactions) {
        try {
            if (!tx.to) continue;
            if (!tx.value || tx.value === 0n) continue;

            const from = normalizeAddress(tx.from);
            const to = normalizeAddress(tx.to);

            if (
                !WATCHED_WALLETS.has(from) &&
                !WATCHED_WALLETS.has(to)
            ) {
                continue;
            }

            const receipt = await provider.getTransactionReceipt(tx.hash);
            if (!receipt || receipt.status !== 1) {
                continue;
            }

            const payload = {
                txid: tx.hash,
                type: "ETH",
                from: tx.from,
                to: tx.to,
                amount: formatEther(tx.value),
                block: blockNumber
            };

            await processWebhook(payload, blockNumber);
        } catch (txErr) {
            logToFile(`Native tx ${tx.hash} error: ${txErr.message}`);
        }
    }
}

async function scanTokenTransfersInBlock(blockNumber) {
    const tokenAddresses = Object.values(TOKENS)
        .map(token => token.address)
        .filter(Boolean);

    if (!tokenAddresses.length) {
        return;
    }

    try {
        const logs = await provider.getLogs({
            fromBlock: blockNumber,
            toBlock: blockNumber,
            address: tokenAddresses,
            topics: [TRANSFER_TOPIC]
        });

        if (!logs.length) {
            return;
        }

        for (const log of logs) {
            try {
                const tokenEntry = getTrackedTokenByAddress(log.address);
                if (!tokenEntry) continue;

                const [symbol, tokenConfig] = tokenEntry;
                const parsed = erc20Interface.parseLog(log);

                const from = normalizeAddress(parsed.args.from);
                const to = normalizeAddress(parsed.args.to);

                if (
                    !WATCHED_WALLETS.has(from) &&
                    !WATCHED_WALLETS.has(to)
                ) {
                    continue;
                }

                const amount = formatUnits(parsed.args.value, tokenConfig.decimals);

                const txReceipt = await provider.getTransactionReceipt(log.transactionHash);
                if (!txReceipt || txReceipt.status !== 1) {
                    continue;
                }

                const payload = {
                    txid: log.transactionHash,
                    type: symbol,
                    token_address: log.address,
                    from: parsed.args.from,
                    to: parsed.args.to,
                    amount,
                    block: blockNumber
                };

                await processWebhook(payload, blockNumber);
            } catch (logErr) {
                logToFile(`Token log ${log.transactionHash} error: ${logErr.message}`);
            }
        }
    } catch (err) {
        logToFile(`Token scan block ${blockNumber} error: ${err.message}`);
        throw err;
    }
}

async function scanBlocks() {
    if (scanning) {
        return;
    }

    scanning = true;

    try {
        if (WATCHED_WALLETS.size === 0) {
            logToFile("No watched wallets loaded");
            return;
        }

        const currentHead = await provider.getBlockNumber();
        const safeCurrent = currentHead - CONFIRMATION_DEPTH;

        if (safeCurrent <= 0) {
            logToFile("Safe block is not ready yet");
            return;
        }

        if (state.lastBlock === 0) {
            state.lastBlock = safeCurrent - 1;
            saveScannerState(state);
        }

        if (state.lastBlock >= safeCurrent) {
            logToFile(`No new safe blocks. lastBlock=${state.lastBlock}, safeCurrent=${safeCurrent}`);
            return;
        }

        for (let n = state.lastBlock + 1; n <= safeCurrent; n++) {
            try {
                await scanNativeTransfersInBlock(n);
                await scanTokenTransfersInBlock(n);

                state.lastBlock = n;

                cleanupProcessedEvents(
                    state,
                    Math.max(0, state.lastBlock - REORG_SAFETY_BLOCKS)
                );

                saveScannerState(state);
                logToFile(`Block ${n} processed successfully`);
            } catch (e) {
                logToFile(`Block ${n} scan error: ${e.message}`);
                break;
            }
        }

        logToFile(`Scan completed up to block ${state.lastBlock}`);
    } catch (err) {
        logToFile(`Scanner fatal error: ${err.message}`);
    } finally {
        scanning = false;
        setTimeout(scanBlocks, SCAN_INTERVAL);
    }
}

(async () => {
    logToFile("ETH scanner starting full initialization...");

    await fetchWalletsAndSave();
    logToFile("Wallets saved to file");

    await syncWallets();
    logToFile(`Wallets synced: ${WATCHED_WALLETS.size}`);

    setInterval(async () => {
        try {
            await fetchWalletsAndSave();
            await syncWallets();
            logToFile(`Wallets refreshed via timer. In-memory count: ${WATCHED_WALLETS.size}`);
        } catch (e) {
            logToFile(`Wallet refresh timer error: ${e.message}`);
        }
    }, WALLET_SYNC_INTERVAL);

    scanBlocks();
})();
