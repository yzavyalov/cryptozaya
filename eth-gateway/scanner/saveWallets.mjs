import fs from "fs";
import path from "path";
import https from "https";
import http from "http";
import dotenv from "dotenv";
import crypto from "crypto";

dotenv.config({ path: path.resolve(process.cwd(), ".env") });

const APP_URL = process.env.APP_URL;
if (!APP_URL) {
    throw new Error("APP_URL is not defined in .env");
}

if (!process.env.WEBHOOK_SECRET) {
    throw new Error("WEBHOOK_SECRET is not defined in .env");
}

const WALLETS_URL = `${APP_URL}/api/internal/eth-wallets`;
const FILE_PATH = path.resolve(process.cwd(), "eth_wallets.json");
const LOG_FILE = path.resolve(process.cwd(), "scan_logs.log");

const payload = "";
const signature = crypto
    .createHmac("sha256", process.env.WEBHOOK_SECRET)
    .update(payload)
    .digest("hex");

function logToFile(...args) {
    try {
        const timestamp = new Date().toISOString();
        const message = args
            .map(a => (typeof a === "object" ? JSON.stringify(a) : a))
            .join(" ");

        fs.appendFileSync(LOG_FILE, `[${timestamp}] ${message}\n`);
    } catch (err) {
        console.error("Logging failed:", err);
    }
}

function ensureWalletFile() {
    if (!fs.existsSync(FILE_PATH)) {
        fs.writeFileSync(FILE_PATH, JSON.stringify([], null, 2));
        logToFile("Wallet file created:", FILE_PATH);
    }
}

function fetchWallets() {
    return new Promise((resolve, reject) => {
        const lib = WALLETS_URL.startsWith("https") ? https : http;

        logToFile("Fetching wallets from:", WALLETS_URL);

        lib.get(WALLETS_URL, {
            headers: {
                "X-Signature": signature,
            },
        }, res => {
            let data = "";
            logToFile("res.statusCode:", res.statusCode);

            res.on("data", chunk => {
                data += chunk;
            });

            res.on("end", () => {
                try {
                    if (res.statusCode !== 200) {
                        return reject(new Error(`HTTP ${res.statusCode}: ${data}`));
                    }

                    const json = JSON.parse(data);

                    if (!Array.isArray(json)) {
                        return reject(new Error("API response is not an array"));
                    }

                    resolve(json);
                } catch (err) {
                    reject(err);
                }
            });
        }).on("error", reject);
    });
}

export async function fetchWalletsAndSave() {
    try {
        ensureWalletFile();

        const wallets = await fetchWallets();

        const normalized = wallets
            .filter(Boolean)
            .map(w => String(w).toLowerCase());

        fs.writeFileSync(FILE_PATH, JSON.stringify(normalized, null, 2));

        logToFile(`Wallets updated. Count: ${normalized.length}`);
        return normalized.length;
    } catch (err) {
        logToFile("Wallet sync error:", err.message);
        throw err;
    }
}
