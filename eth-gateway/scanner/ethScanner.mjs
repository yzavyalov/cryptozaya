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
const WEBHOOK_SECRET = process.env.WEBHOOK_SECRET;

if (!ETH_RPC_URL) throw new Error("ETH_RPC_URL is not set in .env");
if (!APP_URL) throw new Error("APP_URL is not set in .env");
if (!WEBHOOK_SECRET) throw new Error("WEBHOOK_SECRET is not set in .env");

const WEBHOOK_URL = `${APP_URL}/api/crypto/eth-webhook`;

const SCAN_INTERVAL = 30_000;
const CONFIRMATION_DEPTH = 3;
const REORG_SAFETY_BLOCKS = 500;

const logFile = path.resolve(process.cwd(), "scan_logs.log");
const WALLETS_FILE = path.resolve(process.cwd(), "eth_wallets.json");

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

function normalizeAddress(address) {
    try {
        return getAddress(address).toLowerCase();
    } catch {
        return String(address || "").trim().toLowerCase();
    }
}

function blockNumberToHex(blockNumber) {
    return `0x${Number(blockNumber).toString(16)}`;
}

function isTooManyRequestsError(err) {
    const message = String(err?.message || "");
    return (
        message.includes("Too Many Requests") ||
        message.includes("-32005") ||
        message.includes("rate limit")
    );
}

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function rpcWithRetry(fn, context, retries = 3) {
    for (let attempt = 1; attempt <= retries; attempt++) {
        try {
            return await fn();
        } catch (err) {
            if (!isTooManyRequestsError(err) || attempt === retries) {
                throw err;
            }

            const delay = attempt * 5000;

            logToFile("RPC rate limit, retrying:", {
                context,
                attempt,
                retries,
                delay_ms: delay
            });

            await sleep(delay);
        }
    }
}

logToFile("ETH scanner started");

const provider = new JsonRpcProvider(ETH_RPC_URL);

let WATCHED_WALLETS = new Set();
let scanning = false;
let state = loadScannerState();

async function syncWallets() {
    try {
        if (!fs.existsSync(WALLETS_FILE)) {
            await fetchWalletsAndSave();
        }

        const walletsData = fs.readFileSync(WALLETS_FILE, "utf8");
        const wallets = JSON.parse(walletsData);

        WATCHED_WALLETS = new Set(
            wallets
                .filter(Boolean)
                .map(w => normalizeAddress(w))
                .filter(w => w.startsWith("0x") && w.length === 42)
        );

        logToFile("Wallets synced:", {
            count: WATCHED_WALLETS.size
        });

        return WATCHED_WALLETS.size;
    } catch (err) {
        logToFile("Wallet sync error:", err.message);
        return 0;
    }
}

async function refreshWalletsBeforeScan() {
    try {
        await fetchWalletsAndSave();
        await syncWallets();
        return true;
    } catch (err) {
        logToFile("Wallet refresh error:", err.message);
        return false;
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
                    logToFile("Webhook response:", {
                        txid: payload.txid,
                        type: payload.type,
                        statusCode: res.statusCode
                    });

                    if (res.statusCode >= 200 && res.statusCode < 300) {
                        resolve(true);
                    } else {
                        reject(new Error(`Webhook HTTP ${res.statusCode}: ${data}`));
                    }
                });
            });

            req.on("error", err => {
                logToFile("Webhook request error:", {
                    txid: payload.txid,
                    type: payload.type,
                    error: err.message
                });

                reject(err);
            });

            req.setTimeout(20_000, () => {
                req.destroy(new Error("Webhook timeout"));
            });

            req.write(body);
            req.end();
        } catch (e) {
            logToFile("Webhook exception:", {
                txid: payload.txid,
                type: payload.type,
                error: e.message
            });

            reject(e);
        }
    });
}

const erc20Interface = new Interface([
    "event Transfer(address indexed from, address indexed to, uint256 value)"
]);

const TRANSFER_TOPIC = erc20Interface.getEvent("Transfer").topicHash;

function getTrackedTokenByAddress(address) {
    const normalized = normalizeAddress(address);

    return Object.entries(TOKENS).find(([, token]) => {
        if (!token?.address) return false;
        return normalizeAddress(token.address) === normalized;
    });
}

async function processWebhook(payload, blockNumber) {
    if (hasProcessedEvent(state, payload)) {
        logToFile("Duplicate skipped:", {
            txid: payload.txid,
            type: payload.type,
            block: blockNumber
        });
        return;
    }

    logToFile("Payment matched:", payload);

    await sendWebhook(payload);

    markEventProcessed(state, payload, blockNumber);
    saveScannerState(state);

    logToFile("Payment processed:", {
        txid: payload.txid,
        type: payload.type,
        block: blockNumber
    });
}

async function scanNativeTransfersInBlock(blockNumber) {
    const block = await rpcWithRetry(
        () => provider.send("eth_getBlockByNumber", [
            blockNumberToHex(blockNumber),
            true
        ]),
        `eth_getBlockByNumber ${blockNumber}`
    );

    if (!block?.transactions?.length) {
        return;
    }

    for (const tx of block.transactions) {
        try {
            if (!tx?.to) continue;
            if (!tx?.value || BigInt(tx.value) === 0n) continue;

            const from = normalizeAddress(tx.from);
            const to = normalizeAddress(tx.to);

            const fromWatched = WATCHED_WALLETS.has(from);
            const toWatched = WATCHED_WALLETS.has(to);

            if (!fromWatched && !toWatched) continue;

            const valueWei = BigInt(tx.value);

            const receipt = await rpcWithRetry(
                () => provider.getTransactionReceipt(tx.hash),
                `getTransactionReceipt native ${tx.hash}`
            );

            if (!receipt || receipt.status !== 1) continue;

            const payload = {
                txid: tx.hash,
                type: "ETH",
                from: tx.from,
                to: tx.to,
                amount: formatEther(valueWei),
                block: blockNumber
            };

            await processWebhook(payload, blockNumber);
        } catch (txErr) {
            logToFile("Native tx error:", {
                txid: tx?.hash,
                block: blockNumber,
                error: txErr.message
            });
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
        const logs = await rpcWithRetry(
            () => provider.getLogs({
                fromBlock: blockNumber,
                toBlock: blockNumber,
                address: tokenAddresses,
                topics: [TRANSFER_TOPIC]
            }),
            `getLogs token ${blockNumber}`
        );

        if (!logs.length) return;

        for (const log of logs) {
            try {
                const tokenEntry = getTrackedTokenByAddress(log.address);
                if (!tokenEntry) continue;

                const [symbol, tokenConfig] = tokenEntry;
                const parsed = erc20Interface.parseLog(log);

                const from = normalizeAddress(parsed.args.from);
                const to = normalizeAddress(parsed.args.to);

                const fromWatched = WATCHED_WALLETS.has(from);
                const toWatched = WATCHED_WALLETS.has(to);

                if (!fromWatched && !toWatched) continue;

                const txReceipt = await rpcWithRetry(
                    () => provider.getTransactionReceipt(log.transactionHash),
                    `getTransactionReceipt token ${log.transactionHash}`
                );

                if (!txReceipt || txReceipt.status !== 1) continue;

                const amount = formatUnits(parsed.args.value, tokenConfig.decimals);

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
                logToFile("Token log error:", {
                    txid: log?.transactionHash,
                    block: blockNumber,
                    error: logErr.message
                });
            }
        }
    } catch (err) {
        logToFile("Token scan block error:", {
            block: blockNumber,
            error: err.message
        });

        throw err;
    }
}

async function scanBlocks() {
    if (scanning) {
        logToFile("Scan skipped: previous scan still running");
        return;
    }

    scanning = true;

    try {
        await refreshWalletsBeforeScan();

        if (WATCHED_WALLETS.size === 0) {
            logToFile("Scan stopped: no wallets");
            return;
        }

        const currentHead = await rpcWithRetry(
            () => provider.getBlockNumber(),
            "getBlockNumber"
        );

        const safeCurrent = currentHead - CONFIRMATION_DEPTH;

        if (safeCurrent <= 0) {
            logToFile("Safe block is not ready");
            return;
        }

        if (!state.lastBlock || state.lastBlock === 0) {
            state.lastBlock = safeCurrent - 1;
            saveScannerState(state);

            logToFile("Initial lastBlock set:", {
                lastBlock: state.lastBlock
            });
        }

        if (state.lastBlock >= safeCurrent) {
            logToFile("No new blocks:", {
                lastBlock: state.lastBlock,
                safeCurrent
            });
            return;
        }

        const fromBlock = state.lastBlock + 1;
        const toBlock = safeCurrent;

        logToFile("Scan started:", {
            fromBlock,
            toBlock,
            totalBlocks: toBlock - fromBlock + 1,
            wallets: WATCHED_WALLETS.size
        });

        for (let n = fromBlock; n <= toBlock; n++) {
            try {
                await scanNativeTransfersInBlock(n);
                await scanTokenTransfersInBlock(n);

                state.lastBlock = n;

                cleanupProcessedEvents(
                    state,
                    Math.max(0, state.lastBlock - REORG_SAFETY_BLOCKS)
                );

                saveScannerState(state);
            } catch (e) {
                logToFile("Block scan error:", {
                    block: n,
                    error: e.message
                });

                break;
            }
        }

        logToFile("Scan completed:", {
            lastBlock: state.lastBlock
        });
    } catch (err) {
        logToFile("Scanner fatal error:", err.message);
    } finally {
        scanning = false;
        setTimeout(scanBlocks, SCAN_INTERVAL);
    }
}

(async () => {
    logToFile("ETH scanner initialization started");

    try {
        await fetchWalletsAndSave();
        await syncWallets();
    } catch (e) {
        logToFile("Initial wallet sync error:", e.message);
    }

    scanBlocks();
})();
